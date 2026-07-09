<?php

namespace App\Filament\Resources\Units;

use App\Filament\Resources\Units\Pages\ManageUnits;
use App\Filament\Resources\Units\Schemas\UnitForm;
use App\Filament\Resources\Units\Tables\UnitsTable;
use App\Models\Unit;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class UnitResource extends Resource
{
    protected static ?string $model = Unit::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static ?string $recordTitleAttribute = 'name_ar';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'المخزون';
    }

    public static function getNavigationLabel(): string
    {
        return 'الوحدات';
    }

    public static function getModelLabel(): string
    {
        return 'وحدة';
    }

    public static function getPluralModelLabel(): string
    {
        return 'الوحدات';
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }


    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->canManageInventory() === true;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->canManageInventory() === true;
    }
    public static function form(Schema $schema): Schema
    {
        return UnitForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UnitsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUnits::route('/'),
        ];
    }
}
