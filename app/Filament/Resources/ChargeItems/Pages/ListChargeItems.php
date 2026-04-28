<?php

namespace App\Filament\Resources\ChargeItems\Pages;

use App\Filament\Resources\ChargeItems\ChargeItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListChargeItems extends ListRecords
{
    protected static string $resource = ChargeItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
