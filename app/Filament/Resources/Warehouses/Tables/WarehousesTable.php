<?php

namespace App\Filament\Resources\Warehouses\Tables;

use App\Filament\Resources\Warehouses\WarehouseResource;
use App\Models\Warehouse;
use App\Support\Filament\MasterDataStatusActions;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WarehousesTable
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
                    ->label('المستودع')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Warehouse $record): ?string => $record->address),
                TextColumn::make('type')
                    ->label('النوع')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'main' => 'رئيسي',
                        'branch' => 'فرعي',
                        'vehicle' => 'مستودع سيارة',
                        default => $state ?? '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'main' => 'primary',
                        'branch' => 'info',
                        'vehicle' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('vehicle.plate_number')
                    ->label('السيارة المرتبطة')
                    ->searchable()
                    ->placeholder('-'),
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
                SelectFilter::make('type')
                    ->label('نوع المستودع')
                    ->options([
                        'main' => 'رئيسي',
                        'branch' => 'فرعي',
                        'vehicle' => 'سيارة / مستودع متنقل',
                    ]),
                SelectFilter::make('vehicle_id')
                    ->label('السيارة المرتبطة')
                    ->relationship('vehicle', 'plate_number')
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
                        ->label('تعديل المستودع')
                        ->modalHeading('تعديل مستودع')
                        ->slideOver()
                        ->visible(fn (): bool => WarehouseResource::canManageWarehouseStructure()),
                    MasterDataStatusActions::activate('المستودع'),
                    MasterDataStatusActions::deactivate('المستودع'),
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
            ->emptyStateIcon('heroicon-o-building-office-2')
            ->emptyStateHeading('لا توجد مستودعات')
            ->emptyStateDescription('أضف مستودعًا رئيسيًا أو فرعيًا أو مستودع سيارة، أو غيّر عوامل التصفية.');
    }
}
