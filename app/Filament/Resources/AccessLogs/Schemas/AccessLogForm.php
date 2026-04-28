<?php

namespace App\Filament\Resources\AccessLogs\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AccessLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('member_id')
                    ->numeric(),
                TextInput::make('dependent_id')
                    ->numeric(),
                TextInput::make('guest_id')
                    ->numeric(),
                TextInput::make('person_name')
                    ->required(),
                TextInput::make('person_type')
                    ->required(),
                TextInput::make('gate')
                    ->required()
                    ->default('Portaria principal'),
                TextInput::make('status')
                    ->required()
                    ->default('allowed'),
                DateTimePicker::make('checked_at')
                    ->required(),
            ]);
    }
}
