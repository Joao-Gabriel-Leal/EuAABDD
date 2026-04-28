<?php

namespace App\Filament\Resources\Reservations\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Schema;

class ReservationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('member_id')
                    ->required()
                    ->numeric(),
                TextInput::make('reservable_space_id')
                    ->required()
                    ->numeric(),
                TextInput::make('invoice_id')
                    ->numeric(),
                DatePicker::make('reservation_date')
                    ->required(),
                TimePicker::make('starts_at'),
                TimePicker::make('ends_at'),
                TextInput::make('status')
                    ->required()
                    ->default('pending_payment'),
                TextInput::make('total_amount')
                    ->required()
                    ->numeric()
                    ->default(0),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}
