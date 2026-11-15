<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Site-wide settings changed by admins in the admin panel. Read through get()/enabled(), which cache all values.
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    public const CACHE_KEY = 'settings.all';

    /**
     * Log the country of each post view (Admin > Views). On by default.
     */
    public const LOG_VIEW_COUNTRIES = 'log_view_countries';

    /**
     * Values used until an admin changes them.
     *
     * @var array<string, string>
     */
    public const DEFAULTS = [
        self::LOG_VIEW_COUNTRIES => '1',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    public static function get(string $key): ?string
    {
        /** @var array<string, string|null> $values */
        $values = Cache::rememberForever(self::CACHE_KEY, fn (): array => static::query()->pluck('value', 'key')->all());

        return array_key_exists($key, $values) ? $values[$key] : (self::DEFAULTS[$key] ?? null);
    }

    public static function enabled(string $key): bool
    {
        return static::get($key) === '1';
    }

    public static function put(string $key, string|bool|null $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => is_bool($value) ? ($value ? '1' : '0') : $value]);
    }
}
