<?php

namespace App\Filament\Resources\DistributionRoutes\Tables;

use App\Enums\PermissionName;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DistributionRoutesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('الرمز')->searchable()->sortable(),
                TextColumn::make('name')->label('خط التوزيع')->searchable()->sortable(),
                TextColumn::make('area.name_ar')->label('المنطقة')->searchable()->sortable(),
                TextColumn::make('vehicle.plate_number')->label('السيارة')->searchable()->placeholder('-'),
                TextColumn::make('driver.name')->label('السائق')->searchable()->placeholder('-'),
                TextColumn::make('salesRepresentative.name')->label('المندوب')->searchable()->placeholder('-')->toggleable(),

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
                SelectFilter::make('area_id')->label('المنطقة')->relationship('area', 'name_ar')->searchable()->preload(),

                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'active' => 'فعال',
                        'inactive' => 'غير فعال',
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (): bool => auth()->user()?->can(PermissionName::DISTRIBUTION_ROUTES_UPDATE->value) === true)
                    ->label('تعديل')
                    ->modalHeading('تعديل خط توزيع')
                    ->slideOver(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('حذف المحدد')
                        ->visible(fn (): bool => auth()->user()?->can(PermissionName::DISTRIBUTION_ROUTES_DELETE->value) === true),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}