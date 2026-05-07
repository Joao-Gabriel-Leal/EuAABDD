<?php

namespace App\Http\Requests;

use App\Models\ReservableSpace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveReservableSpaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasInternalRole() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $space = $this->route('space') instanceof ReservableSpace ? $this->route('space') : null;

        $this->merge([
            'guest_price' => $this->input('guest_price', $space?->guestPrice() ?? ReservableSpace::DEFAULT_GUEST_PRICE),
            'map_x' => $this->input('map_x', $space?->mapX() ?? ReservableSpace::DEFAULT_MAP_X),
            'map_y' => $this->input('map_y', $space?->mapY() ?? ReservableSpace::DEFAULT_MAP_Y),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'reservable_space_type_id' => ['nullable', 'integer', 'exists:reservable_space_types,id'],
            'type' => ['nullable', 'string', 'max:120'],
            'location' => ['required', 'string', 'max:255'],
            'capacity' => ['required', 'integer', 'min:1', 'max:5000'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i'],
            'included_guests' => ['required', 'integer', 'min:0', 'max:500'],
            'guest_price' => ['required', 'numeric', 'min:0', 'max:9999'],
            'map_x' => ['required', 'integer', 'min:0', 'max:100'],
            'map_y' => ['required', 'integer', 'min:0', 'max:100'],
            'map_note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'image_url' => 'URL da imagem',
            'image_file' => 'upload da imagem',
            'reservable_space_type_id' => 'tipo do espaco',
            'type' => 'tipo do espaco',
            'starts_at' => 'hora inicial',
            'ends_at' => 'hora final',
            'included_guests' => 'convidados inclusos',
            'guest_price' => 'valor por convidado',
            'map_x' => 'posicao horizontal no mapa',
            'map_y' => 'posicao vertical no mapa',
            'map_note' => 'referencia no mapa',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $startsAt = $this->input('starts_at');
            $endsAt = $this->input('ends_at');

            if ($startsAt && $endsAt && $startsAt >= $endsAt) {
                $validator->errors()->add('ends_at', 'A hora final precisa ser maior que a hora inicial.');
            }

            if (! $this->filled('reservable_space_type_id') && ! $this->filled('type')) {
                $validator->errors()->add('reservable_space_type_id', 'Selecione o tipo do espaco.');
            }

            $imageUrl = trim((string) $this->input('image_url'));
            $hasImageInput = $imageUrl !== '' || $this->hasFile('image_file');
            $currentImage = $this->route('space') instanceof ReservableSpace
                ? $this->route('space')->getRawOriginal('image_url')
                : null;

            if (! $hasImageInput && blank($currentImage)) {
                $validator->errors()->add('image_url', 'Informe uma URL externa ou envie uma imagem do espaco.');
            }

            if ($imageUrl !== '' && ! filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                $validator->errors()->add('image_url', 'Informe uma URL externa valida.');
            }
        });
    }
}
