<?php

namespace App\Events;

use App\Models\Staff;

class TwoFactorEmailVerified
{
    public function __construct(public Staff $staff) {}
}
