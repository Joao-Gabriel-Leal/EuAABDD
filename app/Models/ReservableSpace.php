<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class ReservableSpace extends Model
{
    public const DEFAULT_STARTS_AT = '12:00';

    public const DEFAULT_ENDS_AT = '18:00';

    public const DEFAULT_INCLUDED_GUESTS = 4;

    protected $guarded = [];

    protected $casts = [
        'rules' => 'array',
        'is_active' => 'boolean',
    ];

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function operationalRules(): array
    {
        return array_replace($this->defaultOperationalRules(), $this->rules ?? []);
    }

    public function startsAt(): string
    {
        return (string) ($this->operationalRules()['starts_at'] ?? self::DEFAULT_STARTS_AT);
    }

    public function endsAt(): string
    {
        return (string) ($this->operationalRules()['ends_at'] ?? self::DEFAULT_ENDS_AT);
    }

    public function includedGuests(): int
    {
        return (int) ($this->operationalRules()['included_guests'] ?? self::DEFAULT_INCLUDED_GUESTS);
    }

    public function mergeOperationalRules(array $overrides): array
    {
        return array_replace($this->rules ?? [], $overrides);
    }

    public function defaultOperationalRules(): array
    {
        return [
            'starts_at' => self::DEFAULT_STARTS_AT,
            'ends_at' => self::DEFAULT_ENDS_AT,
            'included_guests' => self::DEFAULT_INCLUDED_GUESTS,
        ];
    }

    public static function normalizeImageUrl(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $value = trim((string) $value);

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://') || str_starts_with($value, 'data:')) {
            return $value;
        }

        if (str_starts_with($value, '/storage/')) {
            return $value;
        }

        if (str_starts_with($value, 'storage/')) {
            return '/'.$value;
        }

        if (str_starts_with($value, '/')) {
            return $value;
        }

        return '/storage/'.ltrim($value, '/');
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => self::normalizeImageUrl($value),
            set: fn (?string $value) => self::normalizeImageUrl($value),
        );
    }
}
