<?php

namespace App\Filament\Resources\Benefits;

use App\Filament\Resources\Benefits\Pages\CreateBenefit;
use App\Filament\Resources\Benefits\Pages\EditBenefit;
use App\Filament\Resources\Benefits\Pages\ListBenefits;
use App\Filament\Resources\Benefits\Schemas\BenefitForm;
use App\Filament\Resources\Benefits\Tables\BenefitsTable;
use App\Models\Benefit;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BenefitResource extends Resource
{
    protected static ?string $model = Benefit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $slug = 'beneficios';

    protected static ?string $modelLabel = 'benefício';

    protected static ?string $pluralModelLabel = 'Benefícios';

    protected static ?string $navigationLabel = 'Benefícios';

    protected static string|\UnitEnum|null $navigationGroup = 'Conteúdo';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return BenefitForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BenefitsTable::configure($table);
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
            'index' => ListBenefits::route('/'),
            'create' => CreateBenefit::route('/create'),
            'edit' => EditBenefit::route('/{record}/edit'),
        ];
    }
}
