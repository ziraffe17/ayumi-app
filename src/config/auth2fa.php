<?php

return [
    'ttl_minutes'   => env('EMAIL_2FA_TTL', 10),   // コード有効期限
    'max_attempts'  => env('EMAIL_2FA_MAX_ATTEMPTS', 5),  // 失敗許容回数
    'cooldown_sec'  => env('EMAIL_2FA_COOLDOWN_SEC', 300), // ロック時間(秒) 例:5分
    'trust_days'    => env('EMAIL_2FA_TRUST_DAYS', 30),    // 端末スキップ日数
];
