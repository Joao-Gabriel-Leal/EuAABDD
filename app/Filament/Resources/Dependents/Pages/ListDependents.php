<?php

namespace App\Filament\Resources\Dependents\Pages;

use App\Filament\Resources\Dependents\DependentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDependents extends ListRecords
{
    protected static string $resource = DependentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
