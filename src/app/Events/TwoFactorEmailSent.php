<?php

namespace App\Events;

use App\Models\Staff;

class TwoFactorEmailSent
{
    public function __construct(public Staff $staff) {}
}
