<?php

namespace App\Filament\Resources\VehicleLoads;

use App\Filament\Resources\VehicleLoads\Pages\CreateVehicleLoad;
use App\Filament\Resources\VehicleLoads\Pages\EditVehicleLoad;
use App\Filament\Resources\VehicleLoads\Pages\ListVehicleLoads;
use App\Filament\Resources\VehicleLoads\Pages\ViewVehicleLoad;
use App\Filament\Resources\VehicleLoads\Schemas\VehicleLoadForm;
use App\Filament\Resources\VehicleLoads\Schemas\VehicleLoadInfolist;
use App\Filament\Resources\VehicleLoads\Tables\VehicleLoadsTable;
use App\Models\VehicleLoad;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class VehicleLoadResource extends Resource
{
    protected static ?string $model = VehicleLoad::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static ?string $recordTitleAttribute = 'load_number';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'التخطيط والتجهيز';
    }

    public static function getNavigationLabel(): string
    {
        return 'أوامر تحميل السيارات';
    }

    public static function getModelLabel(): string
    {
        return 'أمر تحميل';
    }

    public static function getPluralModelLabel(): string
    {
        return 'أوامر تحميل السيارات';
    }

    public static function getNavigationSort(): ?int
    {
        return 30;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return VehicleLoadForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VehicleLoadInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VehicleLoadsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVehicleLoads::route('/'),
            'create' => CreateVehicleLoad::route('/create'),
            'view' => ViewVehicleLoad::route('/{record}'),
            'edit' => EditVehicleLoad::route('/{record}/edit'),
        ];
    }
}
