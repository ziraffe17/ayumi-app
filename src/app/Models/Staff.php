<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Contracts\Auth\MustVerifyEmail; // ★追加：メール認証

class Staff extends Authenticatable implements MustVerifyEmail // ★implements を付与
{
    use HasApiTokens, Notifiable;
    // ※FortifyのTOTPは使わない前提なので TwoFactorAuthenticatable は外す
    // use Laravel\Fortify\TwoFactorAuthenticatable;

    protected $table = 'staffs';

    protected $fillable = [
        'name',
        'name_kana',
        'email',
        'password',
        'role',
        'is_active',
        // 'email_verified_at' は fillable でなくてOK（自動更新される）
    ];

    protected $hidden = [
        'password',
        'remember_token',
        // 'two_factor_secret',
        // 'two_factor_recovery_codes',
    ];

    protected $casts = [
        'is_active'     => 'boolean',
        'last_login_at' => 'datetime',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
        'email_verified_at' => 'datetime', // ★メール認証日時
        // 'two_factor_confirmed_at' => 'datetime',
    ];

    // 例: 面談
    public function interviews()
    {
        return $this->hasMany(Interview::class, 'staff_id');
    }
}
