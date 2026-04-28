<?php

namespace App\Filament\Resources\Dependents\Pages;

use App\Filament\Resources\Dependents\DependentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDependent extends EditRecord
{
    protected static string $resource = DependentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
