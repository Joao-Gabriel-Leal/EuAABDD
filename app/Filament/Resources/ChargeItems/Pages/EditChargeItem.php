<?php

namespace App\Filament\Resources\ChargeItems\Pages;

use App\Filament\Resources\ChargeItems\ChargeItemResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditChargeItem extends EditRecord
{
    protected static string $resource = ChargeItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
