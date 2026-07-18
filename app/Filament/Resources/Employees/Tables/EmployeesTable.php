<?php

namespace App\Filament\Resources\Employees\Tables;

use App\Models\Employee;
use App\Support\Filament\MasterDataStatusActions;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EmployeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee_code')
                    ->label('الرمز')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),
                TextColumn::make('name')
                    ->label('الموظف')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Employee $record): ?string => $record->job_title),
                TextColumn::make('type')
                    ->label('النوع التشغيلي')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'driver' => 'سائق',
                        'sales_representative' => 'مندوب مبيعات',
                        'warehouse_keeper' => 'أمين مستودع',
                        'accountant' => 'محاسب',
                        'supervisor' => 'مشرف',
                        default => $state ?? '-',
                    })
                    ->color('primary'),
                TextColumn::make('user.name')
                    ->label('حساب المستخدم')
                    ->searchable()
                    ->placeholder('غير مرتبط')
                    ->description(fn (Employee $record): ?string => $record->user?->email),
                TextColumn::make('phone')
                    ->label('الهاتف')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),
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
                    ->label('نوع الموظف')
                    ->options([
                        'driver' => 'سائق',
                        'sales_representative' => 'مندوب مبيعات',
                        'warehouse_keeper' => 'أمين مستودع',
                        'accountant' => 'محاسب',
                        'supervisor' => 'مشرف',
                    ]),
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
                        ->label('تعديل الموظف')
                        ->modalHeading('تعديل موظف')
                        ->slideOver()
                        ->visible(fn (Employee $record): bool => auth()->user()?->can('update', $record) === true),
                    MasterDataStatusActions::activate('الموظف'),
                    MasterDataStatusActions::deactivate('الموظف'),
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
            ->emptyStateIcon('heroicon-o-user-group')
            ->emptyStateHeading('لا يوجد موظفون')
            ->emptyStateDescription('أضف أول موظف، أو غيّر عوامل التصفية لعرض الموظفين غير الفعالين.');
    }
}
