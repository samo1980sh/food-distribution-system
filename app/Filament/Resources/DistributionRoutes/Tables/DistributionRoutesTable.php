<?php

namespace App\Filament\Resources\DistributionRoutes\Tables;

use App\Models\DistributionRoute;
use App\Support\Filament\MasterDataStatusActions;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DistributionRoutesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('الرمز')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),
                TextColumn::make('name')
                    ->label('خط التوزيع')
                    ->searchable()
                    ->sortable()
                    ->description(fn (DistributionRoute $record): ?string => $record->area?->name_ar),
                TextColumn::make('vehicle.plate_number')
                    ->label('السيارة')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('driver.name')
                    ->label('السائق')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('salesRepresentative.name')
                    ->label('مندوب المبيعات')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('visit_days_summary')
                    ->label('أيام الزيارة')
                    ->state(fn (DistributionRoute $record): string => self::visitDaysLabel($record->visit_days))
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === 'active' ? 'فعال' : 'غير فعال')
                    ->color(fn (?string $state): string => $state === 'active' ? 'success' : 'gray'),
                TextColumn::make('updated_at')
                    ->label('آخر تحديث')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('area_id')
                    ->label('المنطقة')
                    ->relationship('area', 'name_ar')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('vehicle_id')
                    ->label('السيارة')
                    ->relationship('vehicle', 'plate_number')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('driver_id')
                    ->label('السائق')
                    ->relationship('driver', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'active' => 'فعال',
                        'inactive' => 'غير فعال',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->label('تعديل الخط')
                        ->modalHeading('تعديل خط توزيع')
                        ->slideOver()
                        ->visible(fn (DistributionRoute $record): bool => auth()->user()?->can('update', $record) === true),
                    MasterDataStatusActions::activate('خط التوزيع'),
                    MasterDataStatusActions::deactivate('خط التوزيع'),
                ])
                    ->label('الإجراءات')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->button(),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc')
            ->persistSearchInSession()
            ->persistColumnSearchesInSession()
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateIcon('heroicon-o-map')
            ->emptyStateHeading('لا توجد خطوط توزيع')
            ->emptyStateDescription('أضف أول خط، أو غيّر عوامل التصفية للعثور على خط غير فعال.');
    }

    private static function visitDaysLabel(mixed $state): string
    {
        $labels = [
            'saturday' => 'السبت',
            'sunday' => 'الأحد',
            'monday' => 'الإثنين',
            'tuesday' => 'الثلاثاء',
            'wednesday' => 'الأربعاء',
            'thursday' => 'الخميس',
            'friday' => 'الجمعة',
        ];

        $days = is_array($state) ? $state : [];

        if ($days === []) {
            return '-';
        }

        return collect($days)
            ->map(fn (string $day): string => $labels[$day] ?? $day)
            ->implode('، ');
    }
}
