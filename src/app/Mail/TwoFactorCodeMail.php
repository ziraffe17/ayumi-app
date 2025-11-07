<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TwoFactorCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $code;
    public int $ttlMinutes;

    public function __construct(string $code, int $ttlMinutes = 10)
    {
        $this->code = $code;
        $this->ttlMinutes = $ttlMinutes;
        $this->subject('【あゆみ】二段階認証コードのお知らせ');
    }

    public function build()
    {
        // ここを既存の Blade に合わせる（emails.two-factor-code → auth.two-factor.code）
        return $this
            ->view('auth.two-factor.code')
            ->with([
                'code'       => $this->code,
                'ttl' => $this->ttlMinutes,
            ]);
    }
}
