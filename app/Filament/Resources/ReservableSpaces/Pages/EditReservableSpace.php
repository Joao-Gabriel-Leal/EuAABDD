<?php

namespace App\Filament\Resources\ReservableSpaces\Pages;

use App\Filament\Resources\ReservableSpaces\ReservableSpaceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditReservableSpace extends EditRecord
{
    protected static string $resource = ReservableSpaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
