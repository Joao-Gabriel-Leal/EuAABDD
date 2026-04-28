<?php

namespace App\Filament\Resources\ReservableSpaces\Schemas;

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
                FileUpload::make('image_url')
                    ->image(),
                TextInput::make('rules'),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
