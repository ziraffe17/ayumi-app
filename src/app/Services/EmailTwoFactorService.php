<?php

namespace App\Services;

use App\Mail\TwoFactorCodeMail;
use App\Models\Staff;
use App\Events\TwoFactorEmailSent;
use App\Events\TwoFactorEmailVerified;
use App\Events\TwoFactorEmailFailed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\CarbonImmutable;

class EmailTwoFactorService
{
    protected function codeKey(Staff $s): string   { return '2fa_email_'.$s->id; }
    protected function tryKey(Staff $s): string    { return '2fa_email_try_'.$s->id; }
    protected function lockKey(Staff $s): string   { return '2fa_email_lock_'.$s->id; }

    public function generateAndSend(Staff $staff, ?int $ttlMinutes = null): void
    {
        $ttl = $ttlMinutes ?? (int) config('auth2fa.ttl_minutes', 10);

        $code = random_int(100000, 999999);
        $payload = [
            'code'       => (string) $code,
            'expires_at' => now()->addMinutes($ttl),
            // ローテーション検討時のために発行IDもたせておく
            'token_id'   => Str::uuid()->toString(),
        ];

        Cache::put($this->codeKey($staff), $payload, now()->addMinutes($ttl));
        // 試行回数をリセット
        Cache::forget($this->tryKey($staff));

        Mail::to($staff->email)->send(new TwoFactorCodeMail((string)$code, $ttl));
        event(new TwoFactorEmailSent($staff));
    }

    public function isLocked(Staff $staff): bool
    {
        $lockedUntil = Cache::get($this->lockKey($staff));
        return $lockedUntil && now()->lt(CarbonImmutable::parse($lockedUntil));
    }

    public function lock(Staff $staff): void
    {
        $cooldown = (int) config('auth2fa.cooldown_sec', 300);
        Cache::put($this->lockKey($staff), now()->addSeconds($cooldown)->toIso8601String(), now()->addSeconds($cooldown));
    }

    public function verify(Staff $staff, string $code): bool
    {
        if ($this->isLocked($staff)) {
            event(new TwoFactorEmailFailed($staff, 'locked'));
            return false;
        }

        $data = Cache::get($this->codeKey($staff));
        if (!$data) {
            event(new TwoFactorEmailFailed($staff, 'expired'));
            return false;
        }

        $attempts = (int) Cache::increment($this->tryKey($staff));
        $max = (int) config('auth2fa.max_attempts', 5);

        $ok = hash_equals((string) $data['code'], trim($code)) && now()->lt($data['expires_at']);

        if ($ok) {
            Cache::forget($this->codeKey($staff));
            Cache::forget($this->tryKey($staff));
            Cache::forget($this->lockKey($staff));
            event(new TwoFactorEmailVerified($staff));
            return true;
        }

        if ($attempts >= $max) {
            // ロックしてコードも破棄（再送を促す）
            $this->lock($staff);
            Cache::forget($this->codeKey($staff));
            event(new TwoFactorEmailFailed($staff, 'max_attempts'));
        } else {
            event(new TwoFactorEmailFailed($staff, 'mismatch'));
        }

        return false;
    }

    public function resend(Staff $staff, ?int $ttlMinutes = null): void
    {
        // ロック中は送らない（メッセージで案内）
        if ($this->isLocked($staff)) {
            return;
        }
        $this->generateAndSend($staff, $ttlMinutes);
    }

    public function clear(Staff $staff): void
    {
        Cache::forget($this->codeKey($staff));
        Cache::forget($this->tryKey($staff));
        Cache::forget($this->lockKey($staff));
    }
}
