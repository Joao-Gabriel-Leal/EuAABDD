<?php

namespace App\Filament\Resources\Plans\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('segment')
                    ->required(),
                TextInput::make('monthly_family')
                    ->numeric(),
                TextInput::make('monthly_individual')
                    ->numeric(),
                TextInput::make('monthly_under_30')
                    ->numeric(),
                TextInput::make('monthly_special')
                    ->numeric(),
                TextInput::make('included_guests')
                    ->required()
                    ->numeric()
                    ->default(4),
                TextInput::make('included_dependents')
                    ->required()
                    ->numeric()
                    ->default(2),
                TextInput::make('extra_guest_price')
                    ->label('Convite excedente')
                    ->required()
                    ->numeric()
                    ->default(28)
                    ->prefix('R$'),
                TextInput::make('monthly_due_day')
                    ->label('Vencimento padrão')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(28)
                    ->default(8),
                TextInput::make('dependent_extra_price')
                    ->label('Dependente extra')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->prefix('R$'),
                TextInput::make('annual_discount_percent')
                    ->label('Desconto anuidade (%)')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
