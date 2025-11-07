<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class UserLoginController extends Controller
{
    protected AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    public function showLoginForm() 
    { 
        return view('user.auth.login'); 
    }

    public function login(Request $request)
    {
        $this->checkTooManyFailedAttempts($request);

        $credentials = $request->validate([
            'login_code' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
        ], [
            'login_code.required' => 'ログインIDを入力してください',
            'password.required' => 'パスワードを入力してください',
        ]);

        $remember = $request->boolean('remember');
        
        $loginAttempt = Auth::guard('web')->attempt([
            'login_code' => $credentials['login_code'],
            'password' => $credentials['password'],
            'is_active' => 1,
        ], $remember);

        if (!$loginAttempt) {
            $this->incrementLoginAttempts($request);
            
            // 監査ログ（失敗）
            $this->auditService->log(
                actorType: 'user',
                actorId: null,
                action: 'login_failed',
                entity: 'auth',
                entityId: null,
                diffJson: [
                    'login_code' => $credentials['login_code'],
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]
            );

            return back()
                ->withErrors(['login_code' => 'ログインIDまたはパスワードが正しくありません'])
                ->onlyInput('login_code');
        }

        // ログイン成功
        $user = Auth::user();
        
        // 最終ログイン時刻を更新
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        // セッション再生成
        $request->session()->regenerate();
        
        // ログイン試行回数をクリア
        $this->clearLoginAttempts($request);

        // 監査ログ（成功）
        $this->auditService->log(
            actorType: 'user',
            actorId: $user->id,
            action: 'login_success',
            entity: 'auth',
            entityId: $user->id,
            diffJson: [
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'remember' => $remember,
            ]
        );

        \Log::info('User login successful', [
            'user_id' => $user->id,
            'login_code' => $user->login_code,
            'ip_address' => $request->ip(),
        ]);

        // 意図されたURLまたはホームへリダイレクト
        $intended = $request->session()->get('url.intended');
        $request->session()->forget('url.intended');
        
        return redirect()->intended(route('user.home'));
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        
        if ($user) {
            // 監査ログ
            $this->auditService->log(
                actorType: 'user',
                actorId: $user->id,
                action: 'logout',
                entity: 'auth',
                entityId: $user->id,
                diffJson: [
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]
            );

            \Log::info('User logout', [
                'user_id' => $user->id,
                'login_code' => $user->login_code,
            ]);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return redirect()->route('user.login')
            ->with('success', 'ログアウトしました');
    }

    /**
     * ログイン試行回数をチェック
     */
    protected function checkTooManyFailedAttempts(Request $request)
    {
        $key = $this->throttleKey($request);
        
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            
            throw ValidationException::withMessages([
                'login_code' => [
                    "ログイン試行回数が上限に達しました。{$seconds}秒後に再試行してください。"
                ]
            ]);
        }
    }

    /**
     * ログイン試行回数を増加
     */
    protected function incrementLoginAttempts(Request $request)
    {
        RateLimiter::hit($this->throttleKey($request), 900); // 15分間
    }

    /**
     * ログイン試行回数をクリア
     */
    protected function clearLoginAttempts(Request $request)
    {
        RateLimiter::clear($this->throttleKey($request));
    }

    /**
     * レート制限のキーを生成
     */
    protected function throttleKey(Request $request): string
    {
        return 'login.attempts.' . $request->ip();
    }
}
