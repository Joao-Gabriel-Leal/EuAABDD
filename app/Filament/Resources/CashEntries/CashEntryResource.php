<?php

namespace App\Filament\Resources\CashEntries;

use App\Filament\Resources\CashEntries\Pages\CreateCashEntry;
use App\Filament\Resources\CashEntries\Pages\EditCashEntry;
use App\Filament\Resources\CashEntries\Pages\ListCashEntries;
use App\Filament\Resources\CashEntries\Schemas\CashEntryForm;
use App\Filament\Resources\CashEntries\Tables\CashEntriesTable;
use App\Models\CashEntry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CashEntryResource extends Resource
{
    protected static ?string $model = CashEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $slug = 'fluxo-de-caixa';

    protected static ?string $modelLabel = 'lançamento de caixa';

    protected static ?string $pluralModelLabel = 'Fluxo de caixa';

    protected static ?string $navigationLabel = 'Fluxo de caixa';

    protected static string|\UnitEnum|null $navigationGroup = 'Financeiro';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return CashEntryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CashEntriesTable::configure($table);
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
            'index' => ListCashEntries::route('/'),
            'create' => CreateCashEntry::route('/create'),
            'edit' => EditCashEntry::route('/{record}/edit'),
        ];
    }
}
