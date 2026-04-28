<?php

namespace App\Filament\Resources\CashEntries\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CashEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label('Tipo')
                    ->options([
                        'income' => 'Entrada',
                        'expense' => 'Saída',
                    ])
                    ->required(),
                TextInput::make('category')
                    ->label('Categoria')
                    ->required(),
                TextInput::make('description')
                    ->label('Descrição')
                    ->required(),
                TextInput::make('amount')
                    ->label('Valor')
                    ->numeric()
                    ->prefix('R$')
                    ->required(),
                DatePicker::make('entry_date')
                    ->label('Data')
                    ->required(),
                Select::make('status')
                    ->label('Status')
                    ->options([
                        'confirmed' => 'Confirmado',
                        'planned' => 'Previsto',
                        'cancelled' => 'Cancelado',
                    ])
                    ->required()
                    ->default('confirmed'),
            ]);
    }
}
