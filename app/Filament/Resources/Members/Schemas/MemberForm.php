<?php

namespace App\Filament\Resources\Members\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('plan_id')
                    ->numeric(),
                TextInput::make('membership_code')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('cpf')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                TextInput::make('phone')
                    ->tel(),
                TextInput::make('status')
                    ->required()
                    ->default('active'),
                TextInput::make('category')
                    ->required()
                    ->default('Familiar'),
                DatePicker::make('joined_at'),
                TextInput::make('photo_url')
                    ->url(),
                TextInput::make('address'),
            ]);
    }
}
