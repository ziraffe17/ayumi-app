<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /**
         * このアプリでは Fortify は「職員(staff)専用」。
         * 2FA は Fortify(TOTP) ではなく、独自の「メール6桁コード + email2faミドルウェア」で実施する。
         */

        // 職員ログイン画面
        Fortify::loginView(fn () => view('auth.login'));

        // ※ ここから下は “TOTP向け” のフックを使わない：
        // Fortify::twoFactorChallengeView(...);                           // ← 不要（削除）
        // Fortify::redirectUserForTwoFactorAuthenticationUsing(...);      // ← 不要（削除）

        // パスワード/プロファイルの各Action結線（Fortifyの既定機能）
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);                 // resetPasswords()
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);               // updatePasswords()
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class); // updateProfileInformation()
    }
}
