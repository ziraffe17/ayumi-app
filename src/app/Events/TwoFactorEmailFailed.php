<?php

namespace App\Events;

use App\Models\Staff;

class TwoFactorEmailFailed
{
    public function __construct(public Staff $staff, public string $reason = 'mismatch') {}
}
