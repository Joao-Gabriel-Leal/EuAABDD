<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class ValidarCarteirinha extends Page
{
    protected static ?string $title = 'Validação de carteirinha';

    protected static ?string $navigationLabel = 'Validação de carteirinha';

    protected static string|\UnitEnum|null $navigationGroup = 'Portaria';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.validar-carteirinha';
}
