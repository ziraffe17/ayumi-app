<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyReportEvening extends Model
{
    protected $table = 'daily_reports_evening';

    protected $fillable = [
        'user_id','report_date',
        'training_summary','training_reflection','condition_note','other_note',
    ];

    protected $casts = [
        'report_date' => 'date',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }
}
