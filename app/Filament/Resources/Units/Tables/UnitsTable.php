<?php

namespace App\Filament\Resources\Units\Tables;

use App\Models\Unit;
use App\Support\Filament\MasterDataStatusActions;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UnitsTable
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
                    ->label('الوحدة')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('symbol')
                    ->label('الاختصار')
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
                        ->label('تعديل الوحدة')
                        ->modalHeading('تعديل وحدة قياس')
                        ->slideOver()
                        ->visible(fn (Unit $record): bool => auth()->user()?->can('update', $record) === true),
                    MasterDataStatusActions::activate('وحدة القياس'),
                    MasterDataStatusActions::deactivate('وحدة القياس'),
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
            ->emptyStateIcon('heroicon-o-scale')
            ->emptyStateHeading('لا توجد وحدات قياس')
            ->emptyStateDescription('أضف أول وحدة، أو غيّر عوامل التصفية لعرض الوحدات غير الفعالة.');
    }
}
