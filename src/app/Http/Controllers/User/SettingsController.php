<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class SettingsController extends Controller
{
    protected AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * 設定画面表示
     */
    public function index()
    {
        $user = auth()->user();
        
        return view('user.settings.index', compact('user'));
    }

    /**
     * パスワード変更
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'current_password.required' => '現在のパスワードを入力してください',
            'password.required' => '新しいパスワードを入力してください',
            'password.confirmed' => 'パスワード確認が一致しません',
        ]);

        $user = auth()->user();

        // 現在のパスワードを確認
        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors([
                'current_password' => '現在のパスワードが正しくありません'
            ]);
        }

        // 新しいパスワードが現在のパスワードと同じかチェック
        if (Hash::check($request->password, $user->password)) {
            return back()->withErrors([
                'password' => '新しいパスワードは現在のパスワードと異なるものを設定してください'
            ]);
        }

        try {
            // パスワード更新
            $user->update([
                'password' => Hash::make($request->password),
                'password_changed_at' => now(),
            ]);

            // 監査ログ
            $this->auditService->log(
                actorType: 'user',
                actorId: $user->id,
                action: 'update',
                entity: 'users',
                entityId: $user->id,
                diffJson: [
                    'action' => 'password_change',
                    'changed_at' => now()->toISOString()
                ]
            );

            return redirect()->route('user.settings.index')
                ->with('success', 'パスワードを変更しました');

        } catch (\Exception $e) {
            \Log::error('User password change failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withErrors(['error' => 'パスワードの変更に失敗しました'])
                ->withInput();
        }
    }

    /**
     * プロフィール更新
     */
    public function updateProfile(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:users,email,' . auth()->id(),
            'phone' => 'nullable|string|max:20',
        ], [
            'email.required' => 'メールアドレスを入力してください',
            'email.email' => '正しいメールアドレス形式で入力してください',
            'email.unique' => 'このメールアドレスは既に使用されています',
        ]);

        $user = auth()->user();
        $oldData = $user->only(['email', 'phone']);

        try {
            $user->update([
                'email' => $request->email,
                'phone' => $request->phone,
            ]);

            // 監査ログ
            $this->auditService->log(
                actorType: 'user',
                actorId: $user->id,
                action: 'update',
                entity: 'users',
                entityId: $user->id,
                diffJson: [
                    'before' => $oldData,
                    'after' => $user->only(['email', 'phone'])
                ]
            );

            return redirect()->route('user.settings.index')
                ->with('success', 'プロフィールを更新しました');

        } catch (\Exception $e) {
            \Log::error('User profile update failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withErrors(['error' => 'プロフィールの更新に失敗しました'])
                ->withInput();
        }
    }
}