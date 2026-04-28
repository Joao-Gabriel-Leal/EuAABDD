<?php

namespace App\Filament\Resources\Guests\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GuestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('cpf')
                    ->label('CPF')
                    ->searchable(),
                TextColumn::make('member.name')
                    ->label('Associado responsável')
                    ->searchable(),
                TextColumn::make('reservation.space.name')
                    ->label('Reserva/espaço')
                    ->searchable(),
                IconColumn::make('is_extra')
                    ->label('Excedente')
                    ->boolean(),
                TextColumn::make('amount')
                    ->label('Valor')
                    ->money('BRL')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'confirmed' => 'Confirmado',
                        'invited' => 'Convidado',
                        'awaiting_payment' => 'Aguardando pagamento',
                        'used' => 'Usado',
                        default => ucfirst(str_replace('_', ' ', $state)),
                    }),
                TextColumn::make('invitation_code')
                    ->label('Convite')
                    ->searchable(),
                TextColumn::make('checked_in_at')
                    ->label('Entrada')
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
