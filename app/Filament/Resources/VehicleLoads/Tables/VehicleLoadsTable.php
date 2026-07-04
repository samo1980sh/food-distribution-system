<?php

namespace App\Filament\Resources\VehicleLoads\Tables;

use App\Models\VehicleLoad;
use App\Services\Distribution\VehicleLoadService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use RuntimeException;

class VehicleLoadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('load_number')
                    ->label('رقم الأمر')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('load_date')
                    ->label('تاريخ التحميل')
                    ->date('Y-m-d')
                    ->sortable(),

                TextColumn::make('vehicle.plate_number')
                    ->label('السيارة')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('route.name')
                    ->label('خط التوزيع')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('driver.name')
                    ->label('السائق')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('fromWarehouse.name')
                    ->label('من')
                    ->searchable(),

                TextColumn::make('toWarehouse.name')
                    ->label('إلى')
                    ->searchable(),

                TextColumn::make('total_quantity')
                    ->label('إجمالي الكمية')
                    ->numeric(decimalPlaces: 3)
                    ->sortable(),

                TextColumn::make('total_cost')
                    ->label('إجمالي التكلفة')
                    ->money('SYP')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'draft' => 'مسودة',
                        'approved' => 'معتمد',
                        'cancelled' => 'ملغي',
                        'closed' => 'مغلق',
                        default => $state ?? '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'draft' => 'warning',
                        'approved' => 'success',
                        'cancelled' => 'danger',
                        'closed' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('approved_at')
                    ->label('تاريخ الاعتماد')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'draft' => 'مسودة',
                        'approved' => 'معتمد',
                        'cancelled' => 'ملغي',
                        'closed' => 'مغلق',
                    ]),

                SelectFilter::make('vehicle_id')
                    ->label('السيارة')
                    ->relationship('vehicle', 'plate_number')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('اعتماد التحميل')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('اعتماد أمر التحميل')
                    ->modalDescription('سيتم نقل الكميات من المستودع المصدر إلى مستودع السيارة، ولا يمكن تعديل الأمر بعد الاعتماد.')
                    ->visible(fn (VehicleLoad $record): bool => $record->isDraft())
                    ->action(function (VehicleLoad $record): void {
                        try {
                            app(VehicleLoadService::class)->approve($record);

                            Notification::make()
                                ->title('تم اعتماد أمر التحميل بنجاح')
                                ->success()
                                ->send();
                        } catch (RuntimeException $exception) {
                            Notification::make()
                                ->title('تعذر اعتماد أمر التحميل')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('cancel')
                    ->label('إلغاء')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('إلغاء أمر التحميل')
                    ->modalDescription('سيتم عكس حركة المخزون وإرجاع الكميات إلى المستودع المصدر.')
                    ->visible(fn (VehicleLoad $record): bool => $record->isApproved())
                    ->action(function (VehicleLoad $record): void {
                        try {
                            app(VehicleLoadService::class)->cancel($record);

                            Notification::make()
                                ->title('تم إلغاء أمر التحميل بنجاح')
                                ->success()
                                ->send();
                        } catch (RuntimeException $exception) {
                            Notification::make()
                                ->title('تعذر إلغاء أمر التحميل')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                EditAction::make()
                    ->label('تعديل')
                    ->modalHeading('تعديل أمر تحميل')
                    ->slideOver()
                    ->visible(fn (VehicleLoad $record): bool => $record->isDraft()),

                DeleteAction::make()
                    ->label('حذف')
                    ->visible(fn (VehicleLoad $record): bool => $record->isDraft()),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc');
    }
}
