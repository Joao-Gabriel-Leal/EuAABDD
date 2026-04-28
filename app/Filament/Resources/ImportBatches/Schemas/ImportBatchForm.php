<?php

namespace App\Filament\Resources\ImportBatches\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ImportBatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('created_by_user_id')
                    ->label('Criado por')
                    ->relationship('createdBy', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('type')
                    ->label('Tipo')
                    ->options([
                        'members' => 'Associados',
                    ])
                    ->required()
                    ->default('members'),
                TextInput::make('filename')
                    ->label('Arquivo')
                    ->required(),
                Select::make('status')
                    ->label('Status')
                    ->options([
                        'processing' => 'Processando',
                        'finished' => 'Concluída',
                        'failed' => 'Falhou',
                    ])
                    ->required()
                    ->default('processing'),
                TextInput::make('total_rows')
                    ->label('Linhas')
                    ->numeric()
                    ->default(0)
                    ->required(),
                TextInput::make('success_rows')
                    ->label('Sucesso')
                    ->numeric()
                    ->default(0)
                    ->required(),
                TextInput::make('failed_rows')
                    ->label('Erros')
                    ->numeric()
                    ->default(0)
                    ->required(),
                DateTimePicker::make('finished_at')
                    ->label('Finalizada em'),
            ]);
    }
}
