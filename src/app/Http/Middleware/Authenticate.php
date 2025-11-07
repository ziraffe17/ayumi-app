<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        if (!$request->expectsJson()) {
            // URLパスで判定して適切なログイン画面へリダイレクト
            if ($request->is('user/*') || $request->is('api/me/*')) {
                return route('user.login');
            }
            
            if ($request->is('staff/*') || $request->is('api/*')) {
                return route('login'); // Fortifyのデフォルト（職員ログイン）
            }
            
            // デフォルトは利用者ログインへ
            return route('user.login');
        }
        
        return null;
    }
}