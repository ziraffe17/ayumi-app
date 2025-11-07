<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyReportMorning extends Model
{
    protected $table = 'daily_reports_morning';

    protected $fillable = [
        'user_id','report_date',
        'sleep_rating','stress_rating','meal_rating',
        'bed_time_local','wake_time_local','bed_at','wake_at','sleep_minutes',
        'mid_awaken_count','is_early_awaken','is_breakfast_done','is_bathing_done',
        'is_medication_taken','mood_score',
        'sign_good','sign_caution','sign_bad',
        'note',
    ];

    protected $casts = [
        'report_date'        => 'date',
        // bed_time_local と wake_time_local は TIME 型なので文字列として扱う
        'bed_at'             => 'datetime',
        'wake_at'            => 'datetime',
        'sleep_minutes'      => 'integer',
        'mid_awaken_count'   => 'integer',
        'is_early_awaken'    => 'boolean',
        'is_breakfast_done'  => 'boolean',
        'is_bathing_done'    => 'boolean',
        'is_medication_taken'=> 'boolean', // tri-state: null/0/1 → nullを許容
        'mood_score'         => 'integer',
        'sign_good'          => 'integer',
        'sign_caution'       => 'integer',
        'sign_bad'           => 'integer',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }
}
