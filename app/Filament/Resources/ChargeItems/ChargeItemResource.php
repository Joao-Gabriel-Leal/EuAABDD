<?php

namespace App\Filament\Resources\ChargeItems;

use App\Filament\Resources\ChargeItems\Pages\CreateChargeItem;
use App\Filament\Resources\ChargeItems\Pages\EditChargeItem;
use App\Filament\Resources\ChargeItems\Pages\ListChargeItems;
use App\Filament\Resources\ChargeItems\Schemas\ChargeItemForm;
use App\Filament\Resources\ChargeItems\Tables\ChargeItemsTable;
use App\Models\ChargeItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ChargeItemResource extends Resource
{
    protected static ?string $model = ChargeItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ChargeItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ChargeItemsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChargeItems::route('/'),
            'create' => CreateChargeItem::route('/create'),
            'edit' => EditChargeItem::route('/{record}/edit'),
        ];
    }
}
