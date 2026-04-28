<?php

namespace App\Filament\Resources\Proposals\Schemas;

use App\Support\BrazilianMasks;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;

class ProposalForm
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
                TextInput::make('name')
                    ->label('Nome')
                    ->required(),
                TextInput::make('cpf')
                    ->label('CPF')
                    ->mask('999.999.999-99')
                    ->placeholder('000.000.000-00')
                    ->maxLength(14)
                    ->dehydrateStateUsing(fn (?string $state): ?string => BrazilianMasks::formatCpf($state)),
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
                        'new' => 'Nova',
                        'analysis' => 'Em análise',
                        'approved' => 'Aprovada',
                        'rejected' => 'Reprovada',
                    ])
                    ->required()
                    ->default('new'),
                Select::make('signature_status')
                    ->label('Assinatura')
                    ->options([
                        'pending' => 'Pendente',
                        'pending_president_signature' => 'Aguardando presidente',
                        'signed' => 'Assinada',
                    ])
                    ->default('pending'),
                DateTimePicker::make('approved_at')
                    ->label('Aprovada em'),
                DateTimePicker::make('signed_at')
                    ->label('Assinada em'),
                Textarea::make('notes')
                    ->label('Observações')
                    ->columnSpanFull(),
            ]);
    }
}
