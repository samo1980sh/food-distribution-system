<?php

namespace App\Filament\Resources\DailyClosings\Tables;

use App\Enums\OperationSource;
use App\Filament\Resources\DailyClosings\Actions\DailyClosingActions;
use App\Filament\Resources\DailyClosings\DailyClosingResource;
use App\Models\DailyClosing;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DailyClosingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(fn (DailyClosing $record): string => DailyClosingResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('closing_number')
                    ->label('رقم الإغلاق')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                TextColumn::make('operation_source')
                    ->label('مصدر العملية')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => OperationSource::labelFor($state))
                    ->color(fn (mixed $state): string => OperationSource::colorFor($state))
                    ->description(fn (DailyClosing $record): ?string => $record->creator?->name)
                    ->sortable(),

                TextColumn::make('closing_date')
                    ->label('تاريخ الإغلاق')
                    ->date('Y-m-d')
                    ->sortable(),

                TextColumn::make('warehouse.name')
                    ->label('المستودع')
                    ->searchable()
                    ->sortable()
                    ->description(fn (DailyClosing $record): ?string => $record->vehicle?->plate_number),

                TextColumn::make('total_expected_quantity')
                    ->label('الرصيد المتوقع')
                    ->numeric(decimalPlaces: 3)
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('expected_cash_amount')
                    ->label('النقد المتوقع')
                    ->money('SYP')
                    ->sortable(),

                TextColumn::make('cash_difference')
                    ->label('فرق الصندوق')
                    ->money('SYP')
                    ->sortable()
                    ->weight('bold')
                    ->color(fn (mixed $state): string => abs((float) $state) < 0.005 ? 'success' : 'warning'),

                TextColumn::make('field_handover_status')
                    ->label('التسليم الميداني')
                    ->state(fn (DailyClosing $record): string => ! $record->isFieldWorkflow()
                        ? 'غير مطلوب'
                        : ($record->fieldHandoverComplete() ? 'مكتمل' : 'غير مكتمل'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'مكتمل' => 'success',
                        'غير مكتمل' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'draft' => 'قيد المطابقة',
                        'confirmed' => 'معتمد',
                        'cancelled' => 'ملغي',
                        default => $state ?? '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'draft' => 'warning',
                        'confirmed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('route.name')
                    ->label('خط التوزيع')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

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

                TextColumn::make('total_opening_quantity')
                    ->label('رصيد البداية')
                    ->numeric(decimalPlaces: 3)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('total_movement_in_quantity')
                    ->label('الوارد الدفتري')
                    ->numeric(decimalPlaces: 3)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('total_movement_out_quantity')
                    ->label('الصادر الدفتري')
                    ->numeric(decimalPlaces: 3)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('total_sales_amount')
                    ->label('المبيعات')
                    ->money('SYP')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('total_returns_amount')
                    ->label('المرتجعات')
                    ->money('SYP')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('total_collections_amount')
                    ->label('التحصيلات')
                    ->money('SYP')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('total_vehicle_expenses_amount')
                    ->label('مصاريف السيارة')
                    ->money('SYP')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('actual_cash_amount')
                    ->label('النقد الفعلي')
                    ->money('SYP')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('snapshot_at')
                    ->label('تثبيت اللقطة')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('closing_date')
                    ->label('الفترة')
                    ->schema([
                        DatePicker::make('from')->label('من تاريخ')->native(false),
                        DatePicker::make('until')->label('إلى تاريخ')->native(false),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            $data['from'] ?? null,
                            fn (Builder $query, $date): Builder => $query->whereDate('closing_date', '>=', $date),
                        )
                        ->when(
                            $data['until'] ?? null,
                            fn (Builder $query, $date): Builder => $query->whereDate('closing_date', '<=', $date),
                        )),

                SelectFilter::make('operation_source')
                    ->label('مصدر العملية')
                    ->options(OperationSource::options()),

                SelectFilter::make('field_workflow')
                    ->label('مسار الإغلاق')
                    ->options([
                        '1' => 'تسليم ميداني',
                        '0' => 'إغلاق إداري',
                    ]),

                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'draft' => 'قيد المطابقة',
                        'confirmed' => 'معتمد',
                        'cancelled' => 'ملغي',
                    ]),

                SelectFilter::make('vehicle_id')
                    ->label('السيارة')
                    ->relationship('vehicle', 'plate_number')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('warehouse_id')
                    ->label('المستودع')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('sales_representative_id')
                    ->label('مندوب المبيعات')
                    ->relationship('salesRepresentative', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()->label('فتح مساحة الإغلاق'),
                    EditAction::make()
                        ->label('إدخال الجرد وتعديل المسودة')
                        ->visible(fn (DailyClosing $record): bool => auth()->user()?->can('update', $record) === true),
                    DailyClosingActions::refreshTotals(),
                    DailyClosingActions::confirm(),
                    DailyClosingActions::cancel(),
                    DailyClosingActions::print(),
                    DeleteAction::make()
                        ->label('حذف المسودة')
                        ->visible(fn (DailyClosing $record): bool => auth()->user()?->can('delete', $record) === true),
                ])
                    ->label('الإجراءات')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->button(),
            ])
            ->toolbarActions([])
            ->defaultSort('closing_date', 'desc')
            ->persistSearchInSession()
            ->persistColumnSearchesInSession()
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateIcon('heroicon-o-clipboard-document-check')
            ->emptyStateHeading('لا توجد إغلاقات يومية بعد')
            ->emptyStateDescription('أنشئ أول إغلاق يومي، أو غيّر الفترة وعوامل التصفية للعثور على إغلاق سابق.');
    }
}
