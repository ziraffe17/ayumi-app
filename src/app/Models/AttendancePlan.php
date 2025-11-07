<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendancePlan extends Model
{
    protected $table = 'attendance_plans';

    protected $fillable = [
        'user_id','plan_date','plan_time_slot','plan_type','note',
        'is_holiday','holiday_name','template_source',
    ];

    protected $casts = [
        'plan_date'   => 'date',
        'is_holiday'  => 'boolean',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }
}
