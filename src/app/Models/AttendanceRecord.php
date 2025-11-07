<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends Model
{
    protected $table = 'attendance_records';

    protected $fillable = [
        'user_id','record_date','record_time_slot','attendance_type','note','source',
        'is_approved','approved_by','approved_at','approval_note',
        'is_locked','locked_by','locked_at',
    ];

    protected $casts = [
        'record_date' => 'date',
        'is_approved' => 'boolean',
        'is_locked' => 'boolean',
        'approved_at' => 'datetime',
        'locked_at' => 'datetime',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function approvedBy() { return $this->belongsTo(Staff::class, 'approved_by'); }
    public function lockedBy() { return $this->belongsTo(Staff::class, 'locked_by'); }
    
    // 承認可能かチェック
    public function canBeApproved(): bool
    {
        return !$this->is_approved && !$this->is_locked;
    }
    
    // 編集可能かチェック
    public function canBeEdited(): bool
    {
        return !$this->is_approved && !$this->is_locked;
    }
    
    // ロック可能かチェック
    public function canBeLocked(): bool
    {
        return !$this->is_locked;
    }
}
