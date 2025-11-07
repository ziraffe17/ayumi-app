<?php

namespace App\Policies;

use App\Models\AttendanceRecord;
use App\Models\Staff;

class AttendanceRecordPolicy
{
    public function view(Staff $staff, AttendanceRecord $rec): bool { return true; }
    public function update(Staff $staff, AttendanceRecord $rec): bool { return in_array($staff->role, ['admin','staff'], true); }
    public function delete(Staff $staff, AttendanceRecord $rec): bool { return in_array($staff->role, ['admin','staff'], true); }
}
