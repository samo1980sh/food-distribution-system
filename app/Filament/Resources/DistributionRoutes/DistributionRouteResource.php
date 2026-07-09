<?php

namespace App\Filament\Resources\DistributionRoutes;

use App\Filament\Resources\DistributionRoutes\Pages\ManageDistributionRoutes;
use App\Filament\Resources\DistributionRoutes\Schemas\DistributionRouteForm;
use App\Filament\Resources\DistributionRoutes\Tables\DistributionRoutesTable;
use App\Models\DistributionRoute;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class DistributionRouteResource extends Resource
{
    protected static ?string $model = DistributionRoute::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'التوزيع والأسطول';
    }

    public static function getNavigationLabel(): string
    {
        return 'خطوط التوزيع';
    }

    public static function getModelLabel(): string
    {
        return 'خط توزيع';
    }

    public static function getPluralModelLabel(): string
    {
        return 'خطوط التوزيع';
    }

    public static function getNavigationSort(): ?int
    {
        return 20;
    }


    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->canManageDistribution() === true;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->canManageDistribution() === true;
    }
    public static function form(Schema $schema): Schema
    {
        return DistributionRouteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DistributionRoutesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDistributionRoutes::route('/'),
        ];
    }
}
