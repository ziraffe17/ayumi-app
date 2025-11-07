<?php

namespace App\Policies;

use App\Models\AttendancePlan;
use App\Models\Staff;

class AttendancePlanPolicy
{
    // 作成: 利用者は自分分のみ登録可（編集/削除は不可）→ コントローラで user_id=本人 を検証
    // ここでは職員側の権限を定義
    public function view(Staff $staff, AttendancePlan $plan): bool { return true; }
    public function update(Staff $staff, AttendancePlan $plan): bool { return in_array($staff->role, ['admin','staff'], true); }
    public function delete(Staff $staff, AttendancePlan $plan): bool { return in_array($staff->role, ['admin','staff'], true); }
}
