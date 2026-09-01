<?php

namespace App\Filament\Resources\FieldOperationalDayOverrides;

use App\Filament\Resources\FieldOperationalDayOverrides\Pages\ManageFieldOperationalDayOverrides;
use App\Models\FieldOperationalDayOverride;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class FieldOperationalDayOverrideResource extends Resource
{
    protected static ?string $model = FieldOperationalDayOverride::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'التوزيع والأسطول';
    }

    public static function getNavigationLabel(): string
    {
        return 'أيام التشغيل الاستثنائية';
    }

    public static function getModelLabel(): string
    {
        return 'يوم تشغيل استثنائي';
    }

    public static function getPluralModelLabel(): string
    {
        return 'أيام التشغيل الاستثنائية';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            DatePicker::make('operation_date')
                ->label('تاريخ التشغيل')
                ->required()
                ->native(false),
            Select::make('route_id')
                ->label('خط التوزيع')
                ->relationship('route', 'name', modifyQueryUsing: fn ($query) => $query->where('status', 'active'))
                ->searchable()
                ->preload()
                ->required()
                ->native(false),
            Textarea::make('reason')
                ->label('سبب التشغيل الاستثنائي')
                ->required()
                ->rows(3)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('operation_date')->label('التاريخ')->date()->sortable(),
                TextColumn::make('route.name')->label('الخط')->searchable(),
                TextColumn::make('vehicle.name')->label('المركبة'),
                TextColumn::make('salesRepresentative.name')->label('المندوب'),
                TextColumn::make('status')->label('الحالة')->badge()->formatStateUsing(
                    fn (string $state): string => $state === 'active' ? 'فعال' : 'ملغي',
                ),
                TextColumn::make('reason')->label('السبب')->limit(60),
            ])
            ->filters([
                SelectFilter::make('status')->label('الحالة')->options([
                    'active' => 'فعال',
                    'cancelled' => 'ملغي',
                ]),
            ])
            ->recordActions([
                Action::make('cancel')
                    ->label('إلغاء التصريح')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (FieldOperationalDayOverride $record): bool =>
                        $record->isActive() && static::canEdit($record))
                    ->action(fn (FieldOperationalDayOverride $record) => $record->forceFill([
                        'status' => 'cancelled',
                        'cancelled_by' => Auth::id(),
                        'cancelled_at' => now(),
                    ])->save()),
                Action::make('reactivate')
                    ->label('إعادة تفعيل التصريح')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (FieldOperationalDayOverride $record): bool =>
                        ! $record->isActive() && static::canEdit($record))
                    ->action(fn (FieldOperationalDayOverride $record) => $record->forceFill([
                        'status' => 'active',
                    ])->save()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageFieldOperationalDayOverrides::route('/')];
    }
}
