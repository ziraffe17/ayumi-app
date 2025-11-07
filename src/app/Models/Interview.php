<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Interview extends Model
{
    protected $table = 'interviews';

    protected $fillable = [
        'user_id','staff_id','interview_date',
        'summary_enc','detail_enc','next_action',
    ];

    protected $casts = [
        'interview_date' => 'date',
        'summary_enc'    => 'encrypted', // 暗号化
        'detail_enc'     => 'encrypted', // 暗号化
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];

    public function user()  { return $this->belongsTo(User::class); }
    public function staff() { return $this->belongsTo(Staff::class); }
}
