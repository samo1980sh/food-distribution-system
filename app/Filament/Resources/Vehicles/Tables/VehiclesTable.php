<?php

namespace App\Filament\Resources\Vehicles\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
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
                TextColumn::make('code')->label('الرمز')->searchable()->sortable(),
                TextColumn::make('plate_number')->label('رقم اللوحة')->searchable()->sortable(),
                TextColumn::make('name')->label('الوصف')->searchable()->toggleable(),
                TextColumn::make('vehicle_type')->label('النوع')->searchable()->toggleable(),
                TextColumn::make('capacity')->label('السعة')->sortable(),

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

                TextColumn::make('license_expiry_date')->label('انتهاء الترخيص')->date('Y-m-d')->sortable()->toggleable(),
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
                EditAction::make()
                    ->visible(fn (): bool => auth()->user()?->canManageMasterData() === true)
                    ->label('تعديل')
                    ->modalHeading('تعديل سيارة')
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