<?php

namespace App\Filament\Resources\Guests\Schemas;

use App\Support\BrazilianMasks;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class GuestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('reservation_id')
                    ->label('Reserva')
                    ->relationship('reservation', 'id')
                    ->searchable()
                    ->preload(),
                Select::make('member_id')
                    ->label('Associado responsável')
                    ->relationship('member', 'name')
                    ->searchable()
                    ->preload(),
                TextInput::make('name')
                    ->label('Nome')
                    ->required(),
                TextInput::make('cpf')
                    ->label('CPF')
                    ->mask('999.999.999-99')
                    ->maxLength(14)
                    ->dehydrateStateUsing(fn (?string $state): ?string => BrazilianMasks::formatCpf($state)),
                Toggle::make('is_extra')
                    ->label('Convidado excedente')
                    ->required(),
                TextInput::make('amount')
                    ->label('Valor')
                    ->numeric()
                    ->prefix('R$')
                    ->default(0)
                    ->required(),
                Select::make('status')
                    ->label('Status')
                    ->options([
                        'confirmed' => 'Confirmado',
                        'invited' => 'Convidado',
                        'awaiting_payment' => 'Aguardando pagamento',
                        'used' => 'Usado',
                    ])
                    ->required()
                    ->default('invited'),
                TextInput::make('invitation_code')
                    ->label('Código do convite'),
                DateTimePicker::make('checked_in_at')
                    ->label('Entrada registrada em'),
            ]);
    }
}
