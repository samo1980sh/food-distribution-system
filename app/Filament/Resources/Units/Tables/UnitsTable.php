<?php

namespace App\Filament\Resources\Units\Tables;

use App\Enums\PermissionName;
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
                TextColumn::make('code')->label('الرمز')->searchable()->sortable(),
                TextColumn::make('name_ar')->label('الاسم')->searchable()->sortable(),
                TextColumn::make('symbol')->label('الاختصار')->searchable()->toggleable(),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === 'active' ? 'فعال' : 'غير فعال')
                    ->color(fn (?string $state): string => $state === 'active' ? 'success' : 'gray'),
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
                EditAction::make()
                    ->visible(fn (): bool => auth()->user()?->can(PermissionName::UNITS_UPDATE->value) === true)
                    ->label('تعديل')
                    ->modalHeading('تعديل وحدة')
                    ->slideOver(),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc');
    }
}