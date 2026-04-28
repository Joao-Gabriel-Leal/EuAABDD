<?php

namespace App\Filament\Resources\Plans\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('segment')
                    ->searchable(),
                TextColumn::make('monthly_family')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('monthly_individual')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('monthly_under_30')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('monthly_special')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('included_guests')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('included_dependents')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('extra_guest_price')
                    ->money()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean(),
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
