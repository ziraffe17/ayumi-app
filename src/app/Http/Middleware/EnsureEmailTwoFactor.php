<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class EnsureEmailTwoFactor
{
    public function handle(Request $request, Closure $next)
    {
        // ★ 1) すでに通過済みセッション
        if ($request->session()->get('email2fa_passed') === true) {
            return $next($request);
        }

        // ★ 2) 端末が信頼されている（30日スキップ）
        if ($user = $request->user('staff')) {
            $cookieName = 'email2fa_trusted_'.$user->id;
            if ($request->hasCookie($cookieName)) {
                return $next($request); // Cookieは EncryptCookies で暗号化済
            }
        }

        // ★ 3) チャレンジ系ルートは素通し（ループ防止）
        if (Route::is('staff.2fa.email.*')) {
            return $next($request);
        }

        // ★ 4) まだならチャレンジへ
        return redirect()->route('staff.2fa.email.show');
    }
}
