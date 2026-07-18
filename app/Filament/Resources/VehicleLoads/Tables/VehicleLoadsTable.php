<?php

namespace App\Filament\Resources\VehicleLoads\Tables;

use App\Filament\Resources\VehicleLoads\Actions\VehicleLoadActions;
use App\Filament\Resources\VehicleLoads\VehicleLoadResource;
use App\Models\VehicleLoad;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class VehicleLoadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(fn (VehicleLoad $record): string => VehicleLoadResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('load_number')
                    ->label('رقم الأمر')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                TextColumn::make('load_date')
                    ->label('تاريخ التحميل')
                    ->date('Y-m-d')
                    ->sortable(),

                TextColumn::make('vehicle.plate_number')
                    ->label('السيارة')
                    ->searchable()
                    ->sortable()
                    ->description(fn (VehicleLoad $record): ?string => $record->route?->name),

                TextColumn::make('fromWarehouse.name')
                    ->label('المستودع المصدر')
                    ->searchable(),

                TextColumn::make('toWarehouse.name')
                    ->label('مستودع السيارة')
                    ->searchable(),

                TextColumn::make('total_quantity')
                    ->label('إجمالي الكمية')
                    ->numeric(decimalPlaces: 3)
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('total_cost')
                    ->label('إجمالي التكلفة')
                    ->money('SYP')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'draft' => 'مسودة',
                        'approved' => 'معتمد',
                        'cancelled' => 'ملغي',
                        'closed' => 'مغلق',
                        default => $state ?? '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'draft' => 'warning',
                        'approved' => 'success',
                        'cancelled' => 'danger',
                        'closed' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('driver.name')
                    ->label('السائق')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('salesRepresentative.name')
                    ->label('مندوب المبيعات')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('approved_at')
                    ->label('تاريخ الاعتماد')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'draft' => 'مسودة',
                        'approved' => 'معتمد',
                        'cancelled' => 'ملغي',
                        'closed' => 'مغلق',
                    ]),

                SelectFilter::make('vehicle_id')
                    ->label('السيارة')
                    ->relationship('vehicle', 'plate_number')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('from_warehouse_id')
                    ->label('المستودع المصدر')
                    ->relationship('fromWarehouse', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('to_warehouse_id')
                    ->label('مستودع السيارة')
                    ->relationship('toWarehouse', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()->label('عرض التفاصيل'),
                    EditAction::make()
                        ->label('تعديل المسودة')
                        ->visible(fn (VehicleLoad $record): bool => auth()->user()?->can('update', $record) === true),
                    VehicleLoadActions::approve(),
                    VehicleLoadActions::cancel(),
                    VehicleLoadActions::print(),
                    DeleteAction::make()
                        ->label('حذف المسودة')
                        ->visible(fn (VehicleLoad $record): bool => auth()->user()?->can('delete', $record) === true),
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
            ->emptyStateIcon('heroicon-o-truck')
            ->emptyStateHeading('لا توجد أوامر تحميل بعد')
            ->emptyStateDescription('أنشئ أول أمر تحميل سيارة، أو غيّر عوامل التصفية إذا كنت تبحث عن أمر موجود.');
    }
}
