<?php

namespace App\Filament\Resources\Suppliers\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SuppliersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('الرمز')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('name')
                    ->label('المورد')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('contact_person')
                    ->label('مسؤول التواصل')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('mobile')
                    ->label('الجوال')
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
                EditAction::make()
                    ->label('تعديل')
                    ->slideOver(),
            ])
            ->defaultSort('name');
    }
}
