<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'name','name_kana','login_code','email','password',
        'start_date','end_date','care_notes_enc',
        'created_by','updated_by','is_active',
    ];

    protected $hidden = [
        'password','remember_token',
    ];

    protected $casts = [
        'start_date'      => 'date',
        'end_date'        => 'date',
        'last_login_at'   => 'datetime',
        'is_active'       => 'boolean',
        'care_notes_enc'  => 'encrypted', // 暗号化
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    // リレーション
    public function attendancePlans()  { return $this->hasMany(AttendancePlan::class); }
    public function attendanceRecords(){ return $this->hasMany(AttendanceRecord::class); }
    public function morningReports()   { return $this->hasMany(DailyReportMorning::class, 'user_id'); }
    public function eveningReports()   { return $this->hasMany(DailyReportEvening::class, 'user_id'); }
    public function interviews()       { return $this->hasMany(Interview::class, 'user_id'); }
    public function creator()          { return $this->belongsTo(\App\Models\Staff::class, 'created_by'); }
    public function updater()          { return $this->belongsTo(\App\Models\Staff::class, 'updated_by'); }
}
