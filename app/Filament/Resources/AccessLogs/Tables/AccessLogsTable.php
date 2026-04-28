<?php

namespace App\Filament\Resources\AccessLogs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AccessLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('member_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('dependent_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('guest_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('person_name')
                    ->searchable(),
                TextColumn::make('person_type')
                    ->searchable(),
                TextColumn::make('gate')
                    ->searchable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('checked_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
