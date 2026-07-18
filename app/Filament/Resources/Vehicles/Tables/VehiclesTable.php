<?php

namespace App\Filament\Resources\Vehicles\Tables;

use App\Models\Vehicle;
use App\Support\Filament\MasterDataStatusActions;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class VehiclesTable
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
                TextColumn::make('plate_number')
                    ->label('رقم اللوحة')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable()
                    ->description(fn (Vehicle $record): ?string => $record->name),
                TextColumn::make('vehicle_type')
                    ->label('النوع')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('warehouse.name')
                    ->label('مستودع السيارة')
                    ->searchable()
                    ->placeholder('غير مرتبط'),
                TextColumn::make('capacity')
                    ->label('السعة')
                    ->sortable()
                    ->placeholder('-'),
                TextColumn::make('current_odometer')
                    ->label('العداد')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('license_expiry_date')
                    ->label('انتهاء الترخيص')
                    ->date('Y-m-d')
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('insurance_expiry_date')
                    ->label('انتهاء التأمين')
                    ->date('Y-m-d')
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'active' => 'فعالة',
                        'maintenance' => 'صيانة',
                        'inactive' => 'خارج الخدمة',
                        default => $state ?? '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'active' => 'success',
                        'maintenance' => 'warning',
                        'inactive' => 'gray',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'active' => 'فعالة',
                        'maintenance' => 'صيانة',
                        'inactive' => 'خارج الخدمة',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->label('تعديل السيارة')
                        ->modalHeading('تعديل سيارة')
                        ->slideOver()
                        ->visible(fn (Vehicle $record): bool => auth()->user()?->can('update', $record) === true),
                    MasterDataStatusActions::activate('السيارة'),
                    MasterDataStatusActions::deactivate('السيارة'),
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
            ->emptyStateHeading('لا توجد سيارات في الأسطول')
            ->emptyStateDescription('أضف أول سيارة، أو غيّر عوامل التصفية لعرض سيارات الصيانة أو خارج الخدمة.');
    }
}
