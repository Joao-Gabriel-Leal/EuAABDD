<?php

namespace App\Filament\Resources\Invoices\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('member_id')
                    ->required()
                    ->numeric(),
                TextInput::make('number')
                    ->required(),
                TextInput::make('type')
                    ->required()
                    ->default('monthly'),
                TextInput::make('description')
                    ->required(),
                TextInput::make('amount')
                    ->required()
                    ->numeric(),
                DatePicker::make('due_date')
                    ->required(),
                DatePicker::make('paid_at'),
                TextInput::make('status')
                    ->required()
                    ->default('pending'),
                TextInput::make('payment_method'),
                TextInput::make('metadata'),
            ]);
    }
}
