<?php

namespace App\Filament\Resources\CashEntries\Pages;

use App\Filament\Resources\CashEntries\CashEntryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCashEntry extends CreateRecord
{
    protected static string $resource = CashEntryResource::class;
}
