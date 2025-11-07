<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $table = 'holidays';

    public $timestamps = false;      // imported_at のみ任意
    protected $primaryKey = 'holiday_date';
    public $incrementing = false;
    protected $keyType = 'string';   // DATE を文字列扱い

    protected $fillable = [
        'holiday_date','name','source','imported_at',
    ];

    protected $casts = [
        'holiday_date' => 'date',
        'imported_at'  => 'datetime',
    ];
}
