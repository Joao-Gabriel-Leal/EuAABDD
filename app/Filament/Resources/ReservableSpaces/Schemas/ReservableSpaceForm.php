<?php

namespace App\Filament\Resources\ReservableSpaces\Schemas;

use App\Models\ReservableSpace;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
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
                Select::make('reservable_space_type_id')
                    ->label('Tipo')
                    ->relationship('spaceType', 'name', fn ($query) => $query->orderBy('name'))
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('type')
                    ->label('Identificador legado')
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
                TextInput::make('rules.guest_price')
                    ->label('Valor por convidado')
                    ->required()
                    ->numeric()
                    ->default(ReservableSpace::DEFAULT_GUEST_PRICE)
                    ->prefix('R$'),
                TextInput::make('rules.map_x')
                    ->label('Mapa X (%)')
                    ->required()
                    ->numeric()
                    ->default(ReservableSpace::DEFAULT_MAP_X),
                TextInput::make('rules.map_y')
                    ->label('Mapa Y (%)')
                    ->required()
                    ->numeric()
                    ->default(ReservableSpace::DEFAULT_MAP_Y),
                TextInput::make('rules.map_note')
                    ->label('Referencia no mapa')
                    ->maxLength(255),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
