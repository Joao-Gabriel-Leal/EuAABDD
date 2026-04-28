<?php

namespace App\Filament\Resources\Invoices\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class InvoiceForm
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
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('number')
                    ->label('Número')
                    ->required(),
                Select::make('type')
                    ->label('Tipo')
                    ->options([
                        'monthly' => 'Mensalidade',
                        'reservation' => 'Reserva',
                        'invitation' => 'Convite',
                        'extra' => 'Avulsa',
                    ])
                    ->required()
                    ->default('monthly'),
                DatePicker::make('billing_month')
                    ->label('Mês de competência'),
                TextInput::make('description')
                    ->label('Descrição')
                    ->required(),
                TextInput::make('amount')
                    ->label('Valor')
                    ->required()
                    ->numeric(),
                DatePicker::make('due_date')
                    ->label('Vencimento')
                    ->required(),
                DatePicker::make('paid_at')
                    ->label('Pago em'),
                Select::make('status')
                    ->label('Status')
                    ->options([
                        'open' => 'Aberta',
                        'overdue' => 'Vencida',
                        'awaiting_review' => 'Comprovante em análise',
                        'paid' => 'Paga',
                        'cancelled' => 'Cancelada',
                    ])
                    ->required()
                    ->default('open'),
                Select::make('payment_method')
                    ->label('Meio previsto')
                    ->options([
                        'Boleto BRB' => 'Boleto BRB',
                        'Débito em conta BRB' => 'Débito em conta BRB',
                        'QR App AABB' => 'QR App AABB',
                        'Cartão presencial' => 'Cartão presencial',
                    ]),
                TextInput::make('manual_reference')
                    ->label('Referência de baixa'),
            ]);
    }
}
