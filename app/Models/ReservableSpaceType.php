<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ReservableSpaceType extends Model
{
    public const DEFAULT_PIN_COLOR = '#e5163d';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function spaces()
    {
        return $this->hasMany(ReservableSpace::class);
    }

    public static function normalizePinColor(?string $value): string
    {
        $value = strtolower(trim((string) $value));

        return preg_match('/^#[0-9a-f]{6}$/', $value)
            ? $value
            : self::DEFAULT_PIN_COLOR;
    }

    public static function normalizeSlug(?string $value, ?string $fallback = null): string
    {
        $slug = Str::of((string) $value)->ascii()->slug('-')->toString();

        if ($slug !== '') {
            return $slug;
        }

        return Str::of((string) $fallback)->ascii()->slug('-')->toString() ?: 'espaco';
    }

    public static function fallbackColorForSlug(?string $slug): string
    {
        return match (self::normalizeSlug($slug)) {
            'churrasqueira' => '#e65a24',
            'evento', 'salao', 'salao-de-festa' => '#d89b12',
            'lazer', 'piscina' => '#0ea5c6',
            'quadra' => '#12845b',
            default => self::DEFAULT_PIN_COLOR,
        };
    }

    protected static function booted(): void
    {
        static::saving(function (ReservableSpaceType $type): void {
            $type->slug = self::normalizeSlug($type->slug ?: $type->name, $type->name);
            $type->pin_color = self::normalizePinColor($type->pin_color);
        });
    }
}
