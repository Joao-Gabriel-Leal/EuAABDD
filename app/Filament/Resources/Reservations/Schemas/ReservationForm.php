<?php

namespace App\Filament\Resources\Reservations\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
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
                Select::make('member_id')
                    ->label('Associado')
                    ->relationship('member', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('reservable_space_id')
                    ->label('Espaço')
                    ->relationship('space', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->numeric(),
                Select::make('invoice_id')
                    ->label('Cobrança')
                    ->relationship('invoice', 'number')
                    ->searchable(),
                DatePicker::make('reservation_date')
                    ->label('Data')
                    ->required(),
                TimePicker::make('starts_at')
                    ->label('Início'),
                TimePicker::make('ends_at')
                    ->label('Fim'),
                Select::make('status')
                    ->label('Status')
                    ->options([
                        'pending_payment' => 'Aguardando pagamento',
                        'confirmed' => 'Confirmada',
                        'cancelled' => 'Cancelada',
                    ])
                    ->required()
                    ->default('pending_payment'),
                TextInput::make('total_amount')
                    ->label('Valor total')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('guest_quota')
                    ->label('Convidados incluídos')
                    ->required()
                    ->numeric()
                    ->default(4),
                Textarea::make('notes')
                    ->label('Observações')
                    ->columnSpanFull(),
            ]);
    }
}
