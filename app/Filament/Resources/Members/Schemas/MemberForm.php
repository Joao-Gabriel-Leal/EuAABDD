<?php

namespace App\Filament\Resources\Members\Schemas;

use App\Support\BrazilianMasks;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;

class MemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('plan_id')
                    ->label('Plano')
                    ->relationship('plan', 'name')
                    ->searchable()
                    ->preload(),
                TextInput::make('membership_code')
                    ->label('Matrícula')
                    ->required(),
                TextInput::make('name')
                    ->label('Nome')
                    ->required(),
                TextInput::make('cpf')
                    ->label('CPF')
                    ->mask('999.999.999-99')
                    ->placeholder('000.000.000-00')
                    ->maxLength(14)
                    ->dehydrateStateUsing(fn (?string $state): ?string => BrazilianMasks::formatCpf($state))
                    ->required(),
                TextInput::make('email')
                    ->label('E-mail')
                    ->email(),
                TextInput::make('phone')
                    ->label('Telefone')
                    ->tel()
                    ->mask(RawJs::make(<<<'JS'
                        $input.replace(/\D/g, '').length > 10 ? '(99) 99999-9999' : '(99) 9999-9999'
                    JS))
                    ->placeholder('(61) 99999-9999')
                    ->maxLength(15)
                    ->dehydrateStateUsing(fn (?string $state): ?string => BrazilianMasks::formatPhone($state)),
                Select::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Ativo',
                        'blocked' => 'Bloqueado',
                        'cancelled' => 'Cancelado',
                    ])
                    ->required()
                    ->default('active'),
                Select::make('category')
                    ->label('Categoria')
                    ->options([
                        'Familiar' => 'Familiar',
                        'Individual' => 'Individual',
                        'Individual 30 Menos' => 'Individual 30 Menos',
                        'Especial' => 'Especial',
                    ])
                    ->required()
                    ->default('Familiar'),
                TextInput::make('billing_due_day')
                    ->label('Dia de vencimento')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(28),
                Select::make('membership_type')
                    ->label('Tipo')
                    ->options([
                        'associate' => 'Associado',
                        'school' => 'Escolinha',
                        'tenant' => 'Arrendatário',
                    ])
                    ->default('associate'),
                DatePicker::make('joined_at')
                    ->label('Entrada'),
                DatePicker::make('cancelled_at')
                    ->label('Cancelamento'),
                TextInput::make('photo_url')
                    ->label('Foto')
                    ->url(),
                Textarea::make('notes')
                    ->label('Histórico/observações')
                    ->columnSpanFull(),
            ]);
    }
}
