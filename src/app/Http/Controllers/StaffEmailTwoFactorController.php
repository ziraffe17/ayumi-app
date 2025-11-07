<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\EmailTwoFactorService;

class StaffEmailTwoFactorController extends Controller
{
    public function __construct(private EmailTwoFactorService $svc) {}

    /**
     * 入力画面（初回アクセス時に送信、ロック中ならロック表示）
     */
    public function show(Request $request)
    {
        $staff = Auth::guard('staff')->user();
        if (!$staff) {
            return redirect()->route('login');
        }

        // ロック中ならそのままビューへ
        if ($this->svc->isLocked($staff)) {
            return view('auth.two-factor.email', ['locked' => true]);
        }

        // 初回のみ送信
        if (!$request->session()->get('email2fa_sent')) {
            $this->svc->generateAndSend($staff);
            $request->session()->put('email2fa_sent', true);
        }

        return view('auth.two-factor.email');
    }

    /**
     * コード検証
     */
    public function verify(Request $request)
    {
        $request->validate(['code' => ['required','digits:6']]);
        $staff = Auth::guard('staff')->user();
        if (!$staff) return redirect()->route('login');

        if ($this->svc->isLocked($staff)) {
            return back()->withErrors(['code' => '一定時間のロック中です。しばらくしてからお試しください。']);
        }

        if ($this->svc->verify($staff, $request->code)) {
            $request->session()->forget('email2fa_sent');
            $request->session()->put('email2fa_passed', true);

            return redirect()->intended('/staff/home')
                ->with('status', '二段階認証に成功しました。');
        }

        return back()
            ->withErrors(['code' => 'コードが正しくないか、有効期限切れです。'])
            ->withInput();
    }

    /**
     * コード再送
     */
    public function resend(Request $request)
    {
        $staff = Auth::guard('staff')->user();
        if (!$staff) return redirect()->route('login');

        if ($this->svc->isLocked($staff)) {
            return back()->with('error', '一定時間のロック中です。しばらく待ってから再送してください。');
        }

        $this->svc->resend($staff);
        return back()->with('status', '認証コードを再送しました。');
    }

    /**
     * キャンセル（ログアウト）
     */
    public function cancel(Request $request)
    {
        $this->svc->clear(Auth::guard('staff')->user());

        Auth::guard('staff')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('status', '二段階認証をキャンセルしました。');
    }
}
