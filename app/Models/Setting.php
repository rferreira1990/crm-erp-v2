<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class Setting extends Model
{
    use HasFactory;

    private const CACHE_KEY = 'settings.map';
    private const CACHE_TTL_MINUTES = 5;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
    ];

    public static function put(string $key, ?string $value): self
    {
        $setting = static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        static::clearCache();

        return $setting;
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $settings = static::allAsMap();

        if (! array_key_exists($key, $settings)) {
            return $default;
        }

        return $settings[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public static function allAsMap(): array
    {
        try {
            if (! Schema::hasTable('settings')) {
                return [];
            }

            return Cache::remember(self::CACHE_KEY, now()->addMinutes(self::CACHE_TTL_MINUTES), function (): array {
                return static::query()
                    ->pluck('value', 'key')
                    ->toArray();
            });
        } catch (\Throwable) {
            return [];
        }
    }

    public static function forgetByPrefix(string $prefix): int
    {
        $count = static::query()
            ->where('key', 'like', $prefix.'%')
            ->delete();

        static::clearCache();

        return $count;
    }

    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
