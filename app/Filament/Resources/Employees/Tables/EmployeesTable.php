<?php

namespace App\Filament\Resources\Employees\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
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
                TextColumn::make('employee_code')->label('الرمز')->searchable()->sortable(),
                TextColumn::make('name')->label('الاسم')->searchable()->sortable(),
                TextColumn::make('phone')->label('الهاتف')->searchable()->toggleable(),

                TextColumn::make('type')
                    ->label('النوع')
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

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'active' => 'فعال',
                        'inactive' => 'غير فعال',
                        default => $state ?? '-',
                    })
                    ->color(fn (?string $state): string => $state === 'active' ? 'success' : 'gray'),
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
                EditAction::make()
                    ->visible(fn (): bool => auth()->user()?->canManageMasterData() === true)
                    ->label('تعديل')
                    ->modalHeading('تعديل موظف')
                    ->slideOver(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('حذف المحدد')
                        ->visible(fn (): bool => auth()->user()?->canManageMasterData() === true),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}