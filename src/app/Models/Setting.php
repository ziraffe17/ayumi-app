<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $table = 'settings';
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
    ];

    /**
     * 設定値を取得（キャッシュ付き）
     */
    public static function get(string $key, $default = null)
    {
        $cacheKey = 'setting_' . $key;

        return Cache::remember($cacheKey, 3600, function () use ($key, $default) {
            $setting = self::find($key);

            if (!$setting) {
                return $default;
            }

            return self::castValue($setting->value, $setting->type);
        });
    }

    /**
     * 設定値を保存（キャッシュクリア）
     */
    public static function set(string $key, $value, string $type = 'string', ?string $description = null): void
    {
        $stringValue = is_array($value) || is_object($value) ? json_encode($value) : (string)$value;

        self::updateOrCreate(
            ['key' => $key],
            [
                'value' => $stringValue,
                'type' => $type,
                'description' => $description,
            ]
        );

        Cache::forget('setting_' . $key);
    }

    /**
     * 型変換
     */
    protected static function castValue($value, string $type)
    {
        return match ($type) {
            'integer' => (int)$value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true),
            default => $value,
        };
    }
}
