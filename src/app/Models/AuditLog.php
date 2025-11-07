<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'audit_logs';
    public $timestamps = false; // occurred_at を使う

    protected $guarded = []; // 監査なので厳密化したければ fillable でもOK

    protected $casts = [
        'occurred_at' => 'datetime',
    ];
}
