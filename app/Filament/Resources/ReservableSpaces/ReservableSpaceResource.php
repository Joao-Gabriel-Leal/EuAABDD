<?php

namespace App\Filament\Resources\ReservableSpaces;

use App\Filament\Resources\ReservableSpaces\Pages\CreateReservableSpace;
use App\Filament\Resources\ReservableSpaces\Pages\EditReservableSpace;
use App\Filament\Resources\ReservableSpaces\Pages\ListReservableSpaces;
use App\Filament\Resources\ReservableSpaces\Schemas\ReservableSpaceForm;
use App\Filament\Resources\ReservableSpaces\Tables\ReservableSpacesTable;
use App\Models\ReservableSpace;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ReservableSpaceResource extends Resource
{
    protected static ?string $model = ReservableSpace::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $slug = 'espacos';

    protected static ?string $modelLabel = 'espaço reservável';

    protected static ?string $pluralModelLabel = 'Espaços';

    protected static ?string $navigationLabel = 'Espaços';

    protected static string|\UnitEnum|null $navigationGroup = 'Reservas e Convites';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return ReservableSpaceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReservableSpacesTable::configure($table);
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
            'index' => ListReservableSpaces::route('/'),
            'create' => CreateReservableSpace::route('/create'),
            'edit' => EditReservableSpace::route('/{record}/edit'),
        ];
    }
}
