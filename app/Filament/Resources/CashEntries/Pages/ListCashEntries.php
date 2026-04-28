<?php

namespace App\Filament\Resources\CashEntries\Pages;

use App\Filament\Resources\CashEntries\CashEntryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCashEntries extends ListRecords
{
    protected static string $resource = CashEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
