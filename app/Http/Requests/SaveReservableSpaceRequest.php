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

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:120'],
            'location' => ['required', 'string', 'max:255'],
            'capacity' => ['required', 'integer', 'min:1', 'max:5000'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i'],
            'included_guests' => ['required', 'integer', 'min:0', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'image_url' => 'URL da imagem',
            'image_file' => 'upload da imagem',
            'starts_at' => 'hora inicial',
            'ends_at' => 'hora final',
            'included_guests' => 'convidados inclusos',
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
