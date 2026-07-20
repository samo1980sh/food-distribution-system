<?php

namespace App\Filament\Resources\DailyClosings;

use App\Filament\Resources\DailyClosings\Pages\CreateDailyClosing;
use App\Filament\Resources\DailyClosings\Pages\EditDailyClosing;
use App\Filament\Resources\DailyClosings\Pages\ListDailyClosings;
use App\Filament\Resources\DailyClosings\Pages\ViewDailyClosing;
use App\Filament\Resources\DailyClosings\Schemas\DailyClosingForm;
use App\Filament\Resources\DailyClosings\Schemas\DailyClosingInfolist;
use App\Filament\Resources\DailyClosings\Tables\DailyClosingsTable;
use App\Models\DailyClosing;
use App\Services\Authorization\AccessScopeService;
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
        return 'المراجعة والاعتماد';
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

    public static function canCreate(): bool
    {
        return auth()->user()?->can('createOffice', DailyClosing::class) === true;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = app(AccessScopeService::class)->apply(DailyClosing::query())
            ->where('status', 'draft')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return DailyClosingForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DailyClosingInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DailyClosingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDailyClosings::route('/'),
            'create' => CreateDailyClosing::route('/create'),
            'view' => ViewDailyClosing::route('/{record}'),
            'edit' => EditDailyClosing::route('/{record}/edit'),
        ];
    }
}
