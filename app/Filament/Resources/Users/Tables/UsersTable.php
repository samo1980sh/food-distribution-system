<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('البريد الإلكتروني')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('role')
                    ->label('الدور')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'super_admin' => 'مدير النظام',
                        'manager' => 'مدير',
                        'supervisor' => 'مشرف',
                        'warehouse_keeper' => 'أمين مستودع',
                        'accountant' => 'محاسب',
                        default => $state ?? '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'super_admin' => 'danger',
                        'manager' => 'primary',
                        'supervisor' => 'info',
                        'warehouse_keeper' => 'warning',
                        'accountant' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'active' => 'فعّال',
                        'inactive' => 'غير فعّال',
                        default => $state ?? '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('الدور')
                    ->options([
                        'super_admin' => 'مدير النظام',
                        'manager' => 'مدير',
                        'supervisor' => 'مشرف',
                        'warehouse_keeper' => 'أمين مستودع',
                        'accountant' => 'محاسب',
                    ]),

                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'active' => 'فعّال',
                        'inactive' => 'غير فعّال',
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('تعديل')
                    ->modalHeading('تعديل مستخدم')
                    ->slideOver(),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc');
    }
}