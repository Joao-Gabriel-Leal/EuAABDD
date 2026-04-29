<?php

namespace App\Filament\Resources\ReservableSpaces\Schemas;

use App\Models\ReservableSpace;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ReservableSpaceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('type')
                    ->required()
                    ->default('churrasqueira'),
                TextInput::make('location'),
                TextInput::make('capacity')
                    ->required()
                    ->numeric()
                    ->default(20),
                TextInput::make('base_price')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->prefix('$'),
                TextInput::make('image_url')
                    ->helperText('Cole uma URL externa ou mantenha o caminho publico ja salvo.'),
                FileUpload::make('image_file')
                    ->image()
                    ->disk('public')
                    ->directory('reservable-spaces')
                    ->dehydrated(false)
                    ->afterStateUpdated(fn ($state, callable $set) => $state
                        ? $set('image_url', ReservableSpace::normalizeImageUrl($state))
                        : null),
                TextInput::make('rules.starts_at')
                    ->label('Inicio')
                    ->required()
                    ->default(ReservableSpace::DEFAULT_STARTS_AT),
                TextInput::make('rules.ends_at')
                    ->label('Fim')
                    ->required()
                    ->default(ReservableSpace::DEFAULT_ENDS_AT),
                TextInput::make('rules.included_guests')
                    ->label('Convidados inclusos')
                    ->required()
                    ->numeric()
                    ->default(ReservableSpace::DEFAULT_INCLUDED_GUESTS),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
