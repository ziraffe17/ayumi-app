<?php

namespace App\Policies;

use App\Models\User;   // 利用者
use App\Models\Staff;  // 職員

class UserPolicy
{
    // （任意）管理者は全許可のショートカット
    public function before($user, string $ability): bool|null
    {
        if ($user instanceof Staff && $user->role === 'admin') {
            return true;
        }
        return null; // 続行
    }

    // 閲覧：職員は可。利用者本人はコントローラ側で別チェック
    public function view($user, User $targetUser): bool
    {
        // 職員の場合は全利用者を閲覧可能
        if ($user instanceof Staff) {
            return in_array($user->role, ['admin','staff'], true);
        }
        
        // 利用者の場合は自分のデータのみ閲覧可能
        if ($user instanceof User) {
            return $user->id === $targetUser->id;
        }
        
        return false;
    }

    // 更新：職員のみ（ロールで制御）
    public function update($user, User $targetUser): bool
    {
        return $user instanceof Staff && in_array($user->role, ['admin','staff'], true);
    }

    public function create($user): bool
    {
        return $user instanceof Staff && $user->role === 'admin';
    }

    public function delete($user, User $targetUser): bool
    {
        return $user instanceof Staff && $user->role === 'admin';
    }

    // （任意）一覧の可否が必要なら
    public function viewAny($user): bool
    {
        return $user instanceof Staff && in_array($user->role, ['admin','staff'], true);
    }

    // 個人ダッシュボード閲覧（利用者は自分のみ、職員は全員）
    public function viewDashboard($user, User $targetUser): bool
    {
        if ($user instanceof Staff) {
            return true;
        }
        
        if ($user instanceof User) {
            return $user->id === $targetUser->id;
        }
        
        return false;
    }

    // 配慮事項など暗号化データの閲覧（職員のみ）
    public function viewSensitiveData($user, User $targetUser): bool
    {
        return $user instanceof Staff;
    }

    // アカウント有効化/無効化（管理者のみ）
    public function toggleStatus($user, User $targetUser): bool
    {
        return $user instanceof Staff && $user->role === 'admin';
    }

    // パスワードリセット（管理者のみ）
    public function resetPassword($user, User $targetUser): bool
    {
        return $user instanceof Staff && $user->role === 'admin';
    }

    // 日報管理（利用者は自分のみ、職員は全員）
    public function manageReports($user, User $targetUser): bool
    {
        if ($user instanceof Staff) {
            return in_array($user->role, ['admin','staff'], true);
        }
        
        if ($user instanceof User) {
            return $user->id === $targetUser->id;
        }
        
        return false;
    }
}