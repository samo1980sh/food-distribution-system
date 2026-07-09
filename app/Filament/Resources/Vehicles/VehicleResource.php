<?php

namespace App\Filament\Resources\Vehicles;

use App\Filament\Resources\Vehicles\Pages\ManageVehicles;
use App\Filament\Resources\Vehicles\Schemas\VehicleForm;
use App\Filament\Resources\Vehicles\Tables\VehiclesTable;
use App\Models\Vehicle;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class VehicleResource extends Resource
{
    protected static ?string $model = Vehicle::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static ?string $recordTitleAttribute = 'plate_number';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'التوزيع والأسطول';
    }

    public static function getNavigationLabel(): string
    {
        return 'السيارات';
    }

    public static function getModelLabel(): string
    {
        return 'سيارة';
    }

    public static function getPluralModelLabel(): string
    {
        return 'السيارات';
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }


    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->canManageDistribution() === true;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->canManageDistribution() === true;
    }
    public static function canCreate(): bool
    {
        return auth()->user()?->canManageMasterData() === true;
    }

    public static function form(Schema $schema): Schema
    {
        return VehicleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VehiclesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageVehicles::route('/'),
        ];
    }
}
