<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Get a setting value by key.
     */
    public static function get(string $key, $default = null)
    {
        return Cache::rememberForever("setting_{$key}", function () use ($key, $default) {
            $setting = self::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    /**
     * Set a setting value by key.
     */
    public static function set(string $key, $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::put("setting_{$key}", $value);
    }

    public static function getSecret(string $key, $default = null): ?string
    {
        $value = self::get($key, $default);

        if (! is_string($value) || $value === '') {
            return $value;
        }

        try {
            return str_starts_with($value, 'enc:')
                ? Crypt::decryptString(substr($value, 4))
                : $value;
        } catch (\Throwable) {
            return $default;
        }
    }

    public static function setSecret(string $key, ?string $value): void
    {
        self::set($key, $value === null || $value === '' ? $value : 'enc:'.Crypt::encryptString($value));
    }
}
