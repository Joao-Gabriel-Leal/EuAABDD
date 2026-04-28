<?php

namespace App\Filament\Resources\Payments\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('invoice_id')
                    ->label('Cobrança')
                    ->relationship('invoice', 'number')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('amount')
                    ->label('Valor')
                    ->numeric()
                    ->prefix('R$')
                    ->required(),
                Select::make('method')
                    ->label('Meio')
                    ->options([
                        'Boleto BRB' => 'Boleto BRB',
                        'Débito em conta BRB' => 'Débito em conta BRB',
                        'QR App AABB' => 'QR App AABB',
                        'Cartão presencial' => 'Cartão presencial',
                    ])
                    ->required(),
                Select::make('status')
                    ->label('Status')
                    ->options([
                        'paid' => 'Pago',
                        'pending' => 'Pendente',
                        'failed' => 'Falhou',
                    ])
                    ->required()
                    ->default('paid'),
                TextInput::make('transaction_code')
                    ->label('Código da transação'),
                DateTimePicker::make('paid_at')
                    ->label('Pago em'),
                Select::make('confirmed_by_user_id')
                    ->label('Confirmado por')
                    ->relationship('confirmedBy', 'name')
                    ->searchable()
                    ->preload(),
                TextInput::make('proof_path')
                    ->label('Comprovante'),
                Textarea::make('notes')
                    ->label('Observações')
                    ->columnSpanFull(),
                DateTimePicker::make('received_at')
                    ->label('Recebido em'),
            ]);
    }
}
