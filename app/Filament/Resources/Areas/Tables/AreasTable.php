<?php

namespace App\Filament\Resources\Areas\Tables;

use App\Models\Area;
use App\Support\Filament\MasterDataStatusActions;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AreasTable
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
                TextColumn::make('name_ar')
                    ->label('المنطقة')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('city')
                    ->label('المدينة / المحافظة')
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
                        ->label('تعديل المنطقة')
                        ->modalHeading('تعديل منطقة')
                        ->slideOver()
                        ->visible(fn (Area $record): bool => auth()->user()?->can('update', $record) === true),
                    MasterDataStatusActions::activate('المنطقة'),
                    MasterDataStatusActions::deactivate('المنطقة'),
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
            ->emptyStateIcon('heroicon-o-map-pin')
            ->emptyStateHeading('لا توجد مناطق مسجلة')
            ->emptyStateDescription('أضف أول منطقة، أو غيّر عوامل التصفية للعثور على منطقة غير فعالة.');
    }
}
