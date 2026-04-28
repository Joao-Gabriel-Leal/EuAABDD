<?php

namespace App\Filament\Resources\CashEntries\Pages;

use App\Filament\Resources\CashEntries\CashEntryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCashEntry extends EditRecord
{
    protected static string $resource = CashEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
