<?php

namespace App\Filament\Resources\Payments\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice.number')
                    ->label('Cobrança')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('invoice.member.name')
                    ->label('Associado')
                    ->searchable(),
                TextColumn::make('amount')
                    ->label('Valor')
                    ->money('BRL')
                    ->sortable(),
                TextColumn::make('method')
                    ->label('Meio')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'paid' => 'Pago',
                        'pending' => 'Pendente',
                        'failed' => 'Falhou',
                        default => ucfirst(str_replace('_', ' ', $state)),
                    }),
                TextColumn::make('transaction_code')
                    ->label('Transação')
                    ->searchable(),
                TextColumn::make('paid_at')
                    ->label('Pago em')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('received_at')
                    ->label('Recebido em')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
