<?php

namespace App\Filament\Resources\Proposals\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ProposalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('plan_id')
                    ->numeric(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('cpf'),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                TextInput::make('phone')
                    ->tel(),
                TextInput::make('status')
                    ->required()
                    ->default('new'),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}
