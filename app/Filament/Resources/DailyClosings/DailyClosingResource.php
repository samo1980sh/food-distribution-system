<?php

namespace App\Filament\Resources\DailyClosings;

use App\Filament\Resources\DailyClosings\Pages\ManageDailyClosings;
use App\Filament\Resources\DailyClosings\Schemas\DailyClosingForm;
use App\Filament\Resources\DailyClosings\Tables\DailyClosingsTable;
use App\Models\DailyClosing;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class DailyClosingResource extends Resource
{
    protected static ?string $model = DailyClosing::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $recordTitleAttribute = 'closing_number';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'الإغلاق والمطابقة';
    }

    public static function getNavigationLabel(): string
    {
        return 'إغلاق اليوم';
    }

    public static function getModelLabel(): string
    {
        return 'إغلاق يوم';
    }

    public static function getPluralModelLabel(): string
    {
        return 'إغلاقات الأيام';
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }


    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->canManageDailyClosings() === true;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->canManageDailyClosings() === true;
    }
    public static function form(Schema $schema): Schema
    {
        return DailyClosingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DailyClosingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDailyClosings::route('/'),
        ];
    }
}
