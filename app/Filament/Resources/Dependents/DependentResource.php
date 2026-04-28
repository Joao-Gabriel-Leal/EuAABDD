<?php

namespace App\Filament\Resources\Dependents;

use App\Filament\Resources\Dependents\Pages\CreateDependent;
use App\Filament\Resources\Dependents\Pages\EditDependent;
use App\Filament\Resources\Dependents\Pages\ListDependents;
use App\Filament\Resources\Dependents\Schemas\DependentForm;
use App\Filament\Resources\Dependents\Tables\DependentsTable;
use App\Models\Dependent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DependentResource extends Resource
{
    protected static ?string $model = Dependent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $slug = 'dependentes';

    protected static ?string $modelLabel = 'dependente';

    protected static ?string $pluralModelLabel = 'Dependentes';

    protected static ?string $navigationLabel = 'Dependentes';

    protected static string|\UnitEnum|null $navigationGroup = 'Secretaria';

    protected static ?int $navigationSort = 15;

    public static function form(Schema $schema): Schema
    {
        return DependentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DependentsTable::configure($table);
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
            'index' => ListDependents::route('/'),
            'create' => CreateDependent::route('/create'),
            'edit' => EditDependent::route('/{record}/edit'),
        ];
    }
}
