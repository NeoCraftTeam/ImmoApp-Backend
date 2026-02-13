<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @property string $key
 * @property string|null $value
 * @property string|null $label
 * @property string $group
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value', 'label', 'group'];

    /**
     * Get a setting value by key, with config fallback.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("setting.{$key}", 3600, function () use ($key, $default) {
            $setting = static::find($key);

            return $setting?->value ?? $default;
        });
    }

    /**
     * Set a setting value by key.
     */
    public static function set(string $key, mixed $value, ?string $label = null, string $group = 'general'): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => (string) $value, 'label' => $label, 'group' => $group]
        );

        Cache::forget("setting.{$key}");
    }
}
