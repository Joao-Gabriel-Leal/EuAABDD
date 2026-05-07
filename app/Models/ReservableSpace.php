<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;

class ReservableSpace extends Model
{
    public const DEFAULT_STARTS_AT = '12:00';

    public const DEFAULT_ENDS_AT = '18:00';

    public const DEFAULT_INCLUDED_GUESTS = 4;

    public const DEFAULT_GUEST_PRICE = 14;

    public const DEFAULT_MAP_X = 50;

    public const DEFAULT_MAP_Y = 50;

    protected $guarded = [];

    protected $casts = [
        'rules' => 'array',
        'is_active' => 'boolean',
    ];

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function spaceType()
    {
        return $this->belongsTo(ReservableSpaceType::class, 'reservable_space_type_id');
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

    public function guestPrice(): float
    {
        return (float) ($this->operationalRules()['guest_price'] ?? self::DEFAULT_GUEST_PRICE);
    }

    public function mapX(): int
    {
        return max(0, min(100, (int) ($this->operationalRules()['map_x'] ?? self::DEFAULT_MAP_X)));
    }

    public function mapY(): int
    {
        return max(0, min(100, (int) ($this->operationalRules()['map_y'] ?? self::DEFAULT_MAP_Y)));
    }

    public function mapNote(): ?string
    {
        $note = $this->operationalRules()['map_note'] ?? null;

        return blank($note) ? null : (string) $note;
    }

    public function typeName(): string
    {
        if ($this->spaceType?->name) {
            return $this->spaceType->name;
        }

        return Str::of((string) $this->type)->replace('-', ' ')->title()->toString();
    }

    public function typeSlug(): string
    {
        return $this->spaceType?->slug ?: ReservableSpaceType::normalizeSlug($this->type);
    }

    public function pinColor(): string
    {
        return $this->spaceType
            ? ReservableSpaceType::normalizePinColor($this->spaceType->pin_color)
            : ReservableSpaceType::fallbackColorForSlug($this->type);
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
            'guest_price' => self::DEFAULT_GUEST_PRICE,
            'map_x' => self::DEFAULT_MAP_X,
            'map_y' => self::DEFAULT_MAP_Y,
            'map_note' => null,
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

    protected static function booted(): void
    {
        static::saving(function (ReservableSpace $space): void {
            if (! $space->reservable_space_type_id) {
                return;
            }

            $type = $space->relationLoaded('spaceType')
                ? $space->spaceType
                : ReservableSpaceType::find($space->reservable_space_type_id);

            if ($type?->slug) {
                $space->type = $type->slug;
            }
        });
    }
}
