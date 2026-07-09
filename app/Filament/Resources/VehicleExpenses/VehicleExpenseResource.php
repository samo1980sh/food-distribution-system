<?php

namespace App\Filament\Resources\VehicleExpenses;

use App\Filament\Resources\VehicleExpenses\Pages\ManageVehicleExpenses;
use App\Filament\Resources\VehicleExpenses\Schemas\VehicleExpenseForm;
use App\Filament\Resources\VehicleExpenses\Tables\VehicleExpensesTable;
use App\Models\VehicleExpense;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class VehicleExpenseResource extends Resource
{
    protected static ?string $model = VehicleExpense::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $recordTitleAttribute = 'expense_number';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'التوزيع والأسطول';
    }

    public static function getNavigationLabel(): string
    {
        return 'مصاريف السيارات';
    }

    public static function getModelLabel(): string
    {
        return 'مصروف سيارة';
    }

    public static function getPluralModelLabel(): string
    {
        return 'مصاريف السيارات';
    }

    public static function getNavigationSort(): ?int
    {
        return 35;
    }

    public static function form(Schema $schema): Schema
    {
        return VehicleExpenseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VehicleExpensesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageVehicleExpenses::route('/'),
        ];
    }
}