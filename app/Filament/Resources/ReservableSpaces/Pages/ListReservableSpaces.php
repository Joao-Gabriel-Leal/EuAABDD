<?php

namespace App\Filament\Resources\ReservableSpaces\Pages;

use App\Filament\Resources\ReservableSpaces\ReservableSpaceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReservableSpaces extends ListRecords
{
    protected static string $resource = ReservableSpaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
