<?php

namespace App\Filament\Resources\Dependents\Schemas;

use App\Support\BrazilianMasks;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DependentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('member_id')
                    ->label('Associado titular')
                    ->relationship('member', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('name')
                    ->label('Nome')
                    ->required(),
                TextInput::make('cpf')
                    ->label('CPF')
                    ->mask('999.999.999-99')
                    ->maxLength(14)
                    ->dehydrateStateUsing(fn (?string $state): ?string => BrazilianMasks::formatCpf($state)),
                DatePicker::make('birthdate')
                    ->label('Nascimento'),
                TextInput::make('relationship')
                    ->label('Parentesco'),
                Select::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Ativo',
                        'blocked' => 'Bloqueado',
                        'cancelled' => 'Cancelado',
                    ])
                    ->required()
                    ->default('active'),
                Toggle::make('is_free')
                    ->label('Dentro da franquia/cortesia')
                    ->required(),
                TextInput::make('monthly_fee')
                    ->label('Mensalidade extra')
                    ->numeric()
                    ->prefix('R$')
                    ->default(0)
                    ->required(),
                Select::make('access_status')
                    ->label('Acesso')
                    ->options([
                        'allowed' => 'Liberado',
                        'blocked' => 'Bloqueado',
                    ])
                    ->required()
                    ->default('allowed'),
            ]);
    }
}
