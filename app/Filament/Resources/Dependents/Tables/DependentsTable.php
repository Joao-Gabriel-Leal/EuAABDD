<?php

namespace App\Filament\Resources\Dependents\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DependentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('member.name')
                    ->label('Associado')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('cpf')
                    ->label('CPF')
                    ->searchable(),
                TextColumn::make('birthdate')
                    ->label('Nascimento')
                    ->date()
                    ->sortable(),
                TextColumn::make('relationship')
                    ->label('Parentesco')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'Ativo',
                        'blocked' => 'Bloqueado',
                        'cancelled' => 'Cancelado',
                        default => ucfirst(str_replace('_', ' ', $state)),
                    }),
                IconColumn::make('is_free')
                    ->label('Cortesia')
                    ->boolean(),
                TextColumn::make('monthly_fee')
                    ->label('Mensalidade extra')
                    ->money('BRL')
                    ->sortable(),
                TextColumn::make('access_status')
                    ->label('Acesso')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'allowed' ? 'Liberado' : 'Bloqueado'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Ativo',
                        'blocked' => 'Bloqueado',
                        'cancelled' => 'Cancelado',
                    ]),
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
