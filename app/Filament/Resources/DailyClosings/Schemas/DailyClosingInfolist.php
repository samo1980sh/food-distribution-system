<?php

namespace App\Filament\Resources\DailyClosings\Schemas;

use App\Enums\OperationSource;
use App\Models\DailyClosing;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DailyClosingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(4)
            ->components([
                Section::make('هوية الإغلاق ونطاقه')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('closing_number')
                            ->label('رقم الإغلاق')
                            ->copyable()
                            ->weight('bold'),
                        TextEntry::make('operation_source')
                            ->label('مصدر العملية')
                            ->badge()
                            ->formatStateUsing(fn (mixed $state): string => OperationSource::labelFor($state))
                            ->color(fn (mixed $state): string => OperationSource::colorFor($state)),
                        TextEntry::make('status')
                            ->label('الحالة')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => self::statusLabel($state))
                            ->color(fn (?string $state): string => self::statusColor($state)),
                        TextEntry::make('closing_date')->label('تاريخ الإغلاق')->date('Y-m-d'),
                        TextEntry::make('field_workflow')
                            ->label('مسار الإغلاق')
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'تسليم ميداني' : 'إغلاق إداري')
                            ->color(fn (bool $state): string => $state ? 'info' : 'gray'),
                        TextEntry::make('snapshot_at')->label('تثبيت اللقطة')->dateTime('Y-m-d H:i')->placeholder('-'),
                        TextEntry::make('warehouse.name')->label('المستودع'),
                        TextEntry::make('vehicle.plate_number')->label('السيارة')->placeholder('-'),
                        TextEntry::make('route.name')->label('خط التوزيع')->placeholder('-'),
                        TextEntry::make('driver.name')->label('السائق')->placeholder('-'),
                        TextEntry::make('salesRepresentative.name')->label('مندوب المبيعات')->placeholder('-'),
                        TextEntry::make('creator.name')->label('أنشأه')->placeholder('-'),
                        TextEntry::make('confirmer.name')->label('اعتمده')->placeholder('-'),
                        TextEntry::make('confirmed_at')->label('تاريخ الاعتماد')->dateTime('Y-m-d H:i')->placeholder('-'),
                        TextEntry::make('updated_at')->label('آخر تحديث')->dateTime('Y-m-d H:i'),
                    ]),


                Section::make('حالة التسليم الميداني')
                    ->description('يعتمد الإغلاق الميداني بعد أن يسلّم السائق الجرد ويسلّم مندوب المبيعات النقد.')
                    ->icon('heroicon-o-device-phone-mobile')
                    ->columns(4)
                    ->columnSpanFull()
                    ->visible(fn (DailyClosing $record): bool => $record->isFieldWorkflow())
                    ->schema([
                        TextEntry::make('inventory_submitted_at')
                            ->label('تسليم جرد السيارة')
                            ->badge()
                            ->formatStateUsing(fn (mixed $state): string => $state ? 'تم التسليم' : 'بانتظار السائق')
                            ->color(fn (mixed $state): string => $state ? 'success' : 'warning'),
                        TextEntry::make('inventorySubmitter.name')
                            ->label('سلّمه')
                            ->placeholder('-'),
                        TextEntry::make('cash_submitted_at')
                            ->label('تسليم النقد')
                            ->badge()
                            ->formatStateUsing(fn (mixed $state): string => $state ? 'تم التسليم' : 'بانتظار المندوب')
                            ->color(fn (mixed $state): string => $state ? 'success' : 'warning'),
                        TextEntry::make('cashSubmitter.name')
                            ->label('سلّمه')
                            ->placeholder('-'),
                    ]),

                Section::make('مطابقة المخزون الدفتري')
                    ->description('المعادلة الأساسية: رصيد البداية + الوارد - الصادر = الرصيد المتوقع، ثم تتم مقارنته بالجرد الفعلي.')
                    ->icon('heroicon-o-scale')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('total_opening_quantity')
                            ->label('إجمالي رصيد البداية')
                            ->numeric(decimalPlaces: 3),
                        TextEntry::make('total_movement_in_quantity')
                            ->label('إجمالي الوارد')
                            ->numeric(decimalPlaces: 3)
                            ->color('success'),
                        TextEntry::make('total_movement_out_quantity')
                            ->label('إجمالي الصادر')
                            ->numeric(decimalPlaces: 3)
                            ->color('danger'),
                        TextEntry::make('total_expected_quantity')
                            ->label('الرصيد الدفتري المتوقع')
                            ->numeric(decimalPlaces: 3)
                            ->weight('bold'),
                        TextEntry::make('actual_inventory_total')
                            ->label('إجمالي الجرد الفعلي')
                            ->state(fn (DailyClosing $record): ?float => self::actualInventoryTotal($record))
                            ->numeric(decimalPlaces: 3)
                            ->placeholder('غير مكتمل')
                            ->weight('bold'),
                        TextEntry::make('inventory_difference_total')
                            ->label('إجمالي فرق الجرد')
                            ->state(fn (DailyClosing $record): ?float => self::inventoryDifferenceTotal($record))
                            ->numeric(decimalPlaces: 3)
                            ->placeholder('غير مكتمل')
                            ->weight('bold')
                            ->color(fn (mixed $state): string => self::differenceColor($state)),
                        TextEntry::make('counted_items')
                            ->label('المواد التي تم جردها')
                            ->state(fn (DailyClosing $record): string => self::countedItemsLabel($record)),
                        TextEntry::make('total_loaded_quantity')
                            ->label('المحمّل تحليليًا')
                            ->numeric(decimalPlaces: 3),
                        TextEntry::make('total_sold_quantity')
                            ->label('المباع تحليليًا')
                            ->numeric(decimalPlaces: 3),
                        TextEntry::make('total_returned_quantity')
                            ->label('المرتجع تحليليًا')
                            ->numeric(decimalPlaces: 3),
                    ]),

                Section::make('ملخص المبيعات والتحصيلات والمصاريف')
                    ->icon('heroicon-o-chart-bar-square')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('total_sales_amount')->label('إجمالي المبيعات')->money('SYP'),
                        TextEntry::make('total_returns_amount')->label('إجمالي المرتجعات')->money('SYP')->color('danger'),
                        TextEntry::make('net_sales_amount')
                            ->label('صافي المبيعات')
                            ->state(fn (DailyClosing $record): float => max(
                                (float) $record->total_sales_amount - (float) $record->total_returns_amount,
                                0,
                            ))
                            ->money('SYP')
                            ->weight('bold'),
                        TextEntry::make('total_collections_amount')->label('إجمالي التحصيلات')->money('SYP'),
                        TextEntry::make('invoice_cash_amount')->label('نقد الفواتير')->money('SYP'),
                        TextEntry::make('cash_collections_amount')->label('تحصيل نقدي')->money('SYP'),
                        TextEntry::make('bank_transfer_collections_amount')->label('تحويلات مصرفية')->money('SYP'),
                        TextEntry::make('cheque_collections_amount')->label('شيكات')->money('SYP'),
                        TextEntry::make('other_collections_amount')->label('تحصيلات أخرى')->money('SYP'),
                        TextEntry::make('non_cash_collections_amount')->label('إجمالي غير النقدي')->money('SYP'),
                        TextEntry::make('total_vehicle_expenses_amount')->label('إجمالي مصاريف السيارة')->money('SYP')->color('danger'),
                        TextEntry::make('cash_vehicle_expenses_amount')->label('مصاريف نقدية')->money('SYP')->color('danger'),
                        TextEntry::make('non_cash_vehicle_expenses_amount')->label('مصاريف غير نقدية')->money('SYP'),
                    ]),

                Section::make('مطابقة الصندوق')
                    ->description('النقد المتوقع = نقد الفواتير + التحصيلات النقدية - مصاريف السيارة النقدية.')
                    ->icon('heroicon-o-banknotes')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('expected_cash_amount')
                            ->label('النقد المتوقع')
                            ->money('SYP')
                            ->weight('bold'),
                        TextEntry::make('actual_cash_amount')
                            ->label('النقد الفعلي')
                            ->money('SYP')
                            ->weight('bold'),
                        TextEntry::make('cash_difference')
                            ->label('فرق الصندوق')
                            ->money('SYP')
                            ->weight('bold')
                            ->color(fn (mixed $state): string => self::differenceColor($state)),
                        TextEntry::make('cash_notes')
                            ->label('تفسير فرق الصندوق')
                            ->placeholder('لا توجد ملاحظة')
                            ->columnSpanFull(),
                    ]),

                Section::make('تفاصيل جرد المواد')
                    ->icon('heroicon-o-rectangle-stack')
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->columns(6)
                            ->schema([
                                TextEntry::make('product.name_ar')->label('المنتج')->columnSpan(2),
                                TextEntry::make('opening_quantity')->label('رصيد البداية')->numeric(decimalPlaces: 3),
                                TextEntry::make('movement_in_quantity')->label('الوارد')->numeric(decimalPlaces: 3)->color('success'),
                                TextEntry::make('movement_out_quantity')->label('الصادر')->numeric(decimalPlaces: 3)->color('danger'),
                                TextEntry::make('expected_quantity')->label('المتوقع')->numeric(decimalPlaces: 3)->weight('bold'),
                                TextEntry::make('actual_quantity')->label('الفعلي')->numeric(decimalPlaces: 3)->placeholder('غير مدخل')->weight('bold'),
                                TextEntry::make('difference_quantity')
                                    ->label('الفرق')
                                    ->numeric(decimalPlaces: 3)
                                    ->placeholder('-')
                                    ->weight('bold')
                                    ->color(fn (mixed $state): string => self::differenceColor($state)),
                                TextEntry::make('loaded_quantity')->label('المحمّل')->numeric(decimalPlaces: 3),
                                TextEntry::make('sold_quantity')->label('المباع')->numeric(decimalPlaces: 3),
                                TextEntry::make('returned_quantity')->label('المرتجع')->numeric(decimalPlaces: 3),
                                TextEntry::make('notes')->label('ملاحظات المادة')->placeholder('-')->columnSpan(2),
                            ]),
                    ]),

                Section::make('الملاحظات والتدقيق')
                    ->icon('heroicon-o-document-text')
                    ->columnSpanFull()
                    ->collapsible()
                    ->schema([
                        TextEntry::make('notes')->label('ملاحظات الإغلاق')->placeholder('لا توجد ملاحظات'),
                        TextEntry::make('administrative_reason')
                            ->label('بيان / سبب الإدخال الإداري')
                            ->placeholder('لا يوجد - العملية واردة من التطبيق أو من بيانات سابقة')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    private static function actualInventoryTotal(DailyClosing $record): ?float
    {
        $record->loadMissing('items');

        if ($record->items->isEmpty() || $record->items->contains(fn ($item): bool => $item->actual_quantity === null)) {
            return null;
        }

        return round((float) $record->items->sum('actual_quantity'), 3);
    }

    private static function inventoryDifferenceTotal(DailyClosing $record): ?float
    {
        $actual = self::actualInventoryTotal($record);

        return $actual === null
            ? null
            : round($actual - (float) $record->total_expected_quantity, 3);
    }

    private static function countedItemsLabel(DailyClosing $record): string
    {
        $record->loadMissing('items');

        $total = $record->items->count();
        $counted = $record->items->whereNotNull('actual_quantity')->count();

        return $counted.' من '.$total;
    }

    private static function differenceColor(mixed $state): string
    {
        if ($state === null || $state === '') {
            return 'gray';
        }

        return abs((float) $state) < 0.0005 ? 'success' : 'warning';
    }

    private static function statusLabel(?string $status): string
    {
        return match ($status) {
            'draft' => 'مسودة قيد المطابقة',
            'confirmed' => 'معتمد',
            'cancelled' => 'ملغي',
            default => $status ?? '-',
        };
    }

    private static function statusColor(?string $status): string
    {
        return match ($status) {
            'draft' => 'warning',
            'confirmed' => 'success',
            'cancelled' => 'danger',
            default => 'gray',
        };
    }
}
