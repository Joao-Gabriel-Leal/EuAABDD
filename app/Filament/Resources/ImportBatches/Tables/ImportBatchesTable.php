<?php

namespace App\Filament\Resources\ImportBatches\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ImportBatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('filename')
                    ->label('Arquivo')
                    ->searchable(),
                TextColumn::make('createdBy.name')
                    ->label('Criado por')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (string $state): string => $state === 'members' ? 'Associados' : ucfirst($state)),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'processing' => 'Processando',
                        'finished' => 'Concluída',
                        'failed' => 'Falhou',
                        default => ucfirst(str_replace('_', ' ', $state)),
                    }),
                TextColumn::make('total_rows')
                    ->label('Linhas')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('success_rows')
                    ->label('Sucesso')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('failed_rows')
                    ->label('Erros')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('finished_at')
                    ->label('Finalizada em')
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
