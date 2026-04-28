<?php

namespace App\Filament\Resources\Invitations\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class InvitationForm
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
                Select::make('guest_id')
                    ->label('Convidado')
                    ->relationship('guest', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('invoice_id')
                    ->label('Cobrança vinculada')
                    ->relationship('invoice', 'number')
                    ->searchable()
                    ->preload(),
                Select::make('type')
                    ->label('Tipo')
                    ->options([
                        'club_access' => 'Acesso ao clube',
                        'reservation_guest' => 'Convidado de reserva',
                    ])
                    ->required()
                    ->default('club_access'),
                TextInput::make('code')
                    ->label('Código'),
                DatePicker::make('valid_for')
                    ->label('Válido para')
                    ->required(),
                Select::make('status')
                    ->label('Status')
                    ->options([
                        'available' => 'Disponível',
                        'used' => 'Usado',
                        'extra_pending' => 'Excedente pendente',
                        'cancelled' => 'Cancelado',
                    ])
                    ->required()
                    ->default('available'),
                Toggle::make('is_extra')
                    ->label('Convite excedente')
                    ->required(),
                TextInput::make('amount')
                    ->label('Valor')
                    ->numeric()
                    ->prefix('R$')
                    ->default(0)
                    ->required(),
                DateTimePicker::make('used_at')
                    ->label('Usado em'),
            ]);
    }
}
