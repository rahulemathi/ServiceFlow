<?php

namespace App\Models;

use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

#[Fillable(['key', 'value', 'group'])]
class SystemSetting extends Model
{
    protected static string $cacheKey = 'system_settings_all';

    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            $settings = Cache::rememberForever(self::$cacheKey, function () {
                return self::query()->pluck('value', 'key')->all();
            });

            return $settings[$key] ?? $default;
        } catch (\Throwable) {
            return $default;
        }
    }

    public static function set(string $key, mixed $value, string $group = 'branding'): self
    {
        $setting = self::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'group' => $group,
            ]
        );

        Cache::forget(self::$cacheKey);

        return $setting;
    }

    public static function getLogoUrl(): string
    {
        $logo = self::get('brand_logo');

        if ($logo && Storage::disk('public')->exists($logo)) {
            return Storage::disk('public')->url($logo);
        }

        if ($logo && filter_var($logo, FILTER_VALIDATE_URL)) {
            return $logo;
        }

        return asset('images/serviceflow-logo.svg');
    }

    public static function getFaviconUrl(): string
    {
        $favicon = self::get('favicon');

        if ($favicon && Storage::disk('public')->exists($favicon)) {
            return Storage::disk('public')->url($favicon);
        }

        return asset('favicon.svg');
    }

    /**
     * @return array<int|string, string>
     */
    public static function getPrimaryColor(): array
    {
        $color = (string) self::get('primary_color', 'blue');

        return match ($color) {
            'emerald' => Color::Emerald,
            'indigo' => Color::Indigo,
            'amber' => Color::Amber,
            'rose' => Color::Rose,
            'violet' => Color::Violet,
            'cyan' => Color::Cyan,
            'teal' => Color::Teal,
            default => Color::Blue,
        };
    }
}
