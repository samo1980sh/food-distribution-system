<?php

namespace App\Filament\Resources\VehicleLoads\Schemas;

use App\Models\VehicleLoad;
use App\Models\VehicleLoadItem;
use App\Support\Formatting\QuantityFormatter;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VehicleLoadInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('ملخص أمر التحميل')
                    ->icon('heroicon-o-truck')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('load_number')->label('رقم الأمر')->copyable(),
                        TextEntry::make('status')
                            ->label('الحالة')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => self::statusLabel($state))
                            ->color(fn (?string $state): string => self::statusColor($state)),
                        TextEntry::make('load_date')->label('تاريخ التحميل')->date('Y-m-d'),
                        TextEntry::make('vehicle.name')->label('السيارة'),
                        TextEntry::make('route.name')->label('خط التوزيع')->placeholder('-'),
                        TextEntry::make('driver.name')->label('السائق')->placeholder('-'),
                        TextEntry::make('salesRepresentative.name')->label('مندوب المبيعات')->placeholder('-'),
                        TextEntry::make('fromWarehouse.name')->label('المستودع المصدر'),
                        TextEntry::make('toWarehouse.name')->label('مستودع السيارة'),
                        TextEntry::make('creator.name')->label('أنشأه')->placeholder('-'),
                        TextEntry::make('approver.name')->label('اعتمده')->placeholder('-'),
                        TextEntry::make('approved_at')->label('تاريخ الاعتماد')->dateTime('Y-m-d H:i')->placeholder('-'),
                    ]),

                Section::make('ملخص المخزون')
                    ->icon('heroicon-o-calculator')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('total_quantity')
                            ->label('إجمالي الكمية')
                            ->state(fn (VehicleLoad $record): string => QuantityFormatter::format(
                                (float) $record->total_quantity,
                            ))
                            ->weight('bold'),
                        TextEntry::make('total_cost')
                            ->label('إجمالي التكلفة')
                            ->money('SYP')
                            ->weight('bold'),
                    ]),

                Section::make('مواد التحميل')
                    ->icon('heroicon-o-rectangle-stack')
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->columns(7)
                            ->schema([
                                TextEntry::make('product.name_ar')->label('المنتج')->columnSpan(2),
                                TextEntry::make('quantity')
                                    ->label('الكمية')
                                    ->state(fn (VehicleLoadItem $record): string => QuantityFormatter::formatWithUnit(
                                        (float) $record->quantity,
                                        $record->product?->unit,
                                    )),
                                TextEntry::make('unit_cost')->label('تكلفة الوحدة')->money('SYP'),
                                TextEntry::make('total_cost')->label('الإجمالي')->money('SYP')->weight('bold'),
                                TextEntry::make('batch_number')->label('التشغيلة')->placeholder('-'),
                                TextEntry::make('expiry_date')->label('الصلاحية')->date('Y-m-d')->placeholder('-'),
                            ]),
                    ]),

                Section::make('استلام العهدة')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('handover_status')
                            ->label('نتيجة الاستلام')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => self::handoverStatusLabel($state))
                            ->color(fn (?string $state): string => self::handoverStatusColor($state)),
                        TextEntry::make('handover_by')
                            ->label('استلم بواسطة')
                            ->state(fn (VehicleLoad $record): string => self::handoverByLabel($record))
                            ->placeholder('-')
                            ->visible(fn ($record): bool => filled($record?->handover_status) && $record?->handover_status !== 'pending'),
                        TextEntry::make('handover_at')
                            ->label('وقت الاستلام')
                            ->dateTime('Y-m-d H:i')
                            ->placeholder('-')
                            ->visible(fn ($record): bool => filled($record?->handover_status) && $record?->handover_status !== 'pending'),
                        TextEntry::make('handover_notes')
                            ->label('ملاحظات الاستلام')
                            ->placeholder('لا توجد ملاحظات')
                            ->columnSpanFull()
                            ->visible(fn ($record): bool => filled($record?->handover_status) && $record?->handover_status !== 'pending'),
                    ]),

                Section::make('تفاصيل استلام المواد')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->columnSpanFull()
                    ->visible(fn ($record): bool => filled($record?->handover_status) && $record?->handover_status !== 'pending')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->hiddenLabel()
                            ->columns(6)
                            ->schema([
                                TextEntry::make('product.name_ar')->label('المنتج')->columnSpan(2),
                                TextEntry::make('batch_number')->label('التشغيلة')->placeholder('-'),
                                TextEntry::make('quantity')
                                    ->label('الكمية المحمّلة')
                                    ->state(fn (VehicleLoadItem $record): string => QuantityFormatter::formatWithUnit(
                                        (float) $record->quantity,
                                        $record->product?->unit,
                                    )),
                                TextEntry::make('received_quantity')
                                    ->label('الكمية المستلمة')
                                    ->state(fn (VehicleLoadItem $record): string => QuantityFormatter::formatWithUnit(
                                        $record->received_quantity === null ? null : (float) $record->received_quantity,
                                        $record->product?->unit,
                                    ))
                                    ->placeholder('-'),
                                TextEntry::make('handover_difference')
                                    ->label('الفرق')
                                    ->state(fn (VehicleLoadItem $record): string => QuantityFormatter::formatDifference(
                                        $record->received_quantity === null
                                            ? null
                                            : (float) $record->received_quantity - (float) $record->quantity,
                                        $record->product?->unit,
                                    ))
                                    ->color(fn (mixed $state): string => self::handoverDifferenceColor($state)),
                                TextEntry::make('handover_note')
                                    ->label('ملاحظة الاستلام')
                                    ->placeholder('لا توجد ملاحظة')
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Section::make('ملاحظات وتدقيق')
                    ->icon('heroicon-o-document-text')
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsible()
                    ->schema([
                        TextEntry::make('notes')->label('الملاحظات')->placeholder('لا توجد ملاحظات')->columnSpanFull(),
                        TextEntry::make('canceller.name')
                            ->label('ألغاه')
                            ->placeholder('-')
                            ->visible(fn ($record): bool => $record?->status === 'cancelled'),
                        TextEntry::make('cancelled_at')
                            ->label('تاريخ الإلغاء')
                            ->dateTime('Y-m-d H:i')
                            ->placeholder('-')
                            ->visible(fn ($record): bool => $record?->status === 'cancelled'),
                        TextEntry::make('cancellation_reason')
                            ->label('سبب الإلغاء')
                            ->placeholder('غير مسجل - إلغاء سابق')
                            ->visible(fn ($record): bool => $record?->status === 'cancelled')
                            ->columnSpanFull(),
                        TextEntry::make('created_at')->label('تاريخ الإنشاء')->dateTime('Y-m-d H:i'),
                        TextEntry::make('updated_at')->label('آخر تحديث')->dateTime('Y-m-d H:i'),
                    ]),
            ]);
    }

    private static function statusLabel(?string $status): string
    {
        return match ($status) {
            'draft' => 'مسودة',
            'approved' => 'معتمد',
            'cancelled' => 'ملغي',
            'closed' => 'مغلق',
            default => $status ?? '-',
        };
    }

    private static function statusColor(?string $status): string
    {
        return match ($status) {
            'draft' => 'warning',
            'approved' => 'success',
            'cancelled' => 'danger',
            'closed' => 'gray',
            default => 'gray',
        };
    }

    private static function handoverStatusLabel(?string $status): string
    {
        return match ($status) {
            'pending' => 'بانتظار الاستلام',
            'received' => 'مستلم مطابق',
            'discrepancy' => 'فروقات عند الاستلام',
            default => $status ?? '-',
        };
    }

    private static function handoverStatusColor(?string $status): string
    {
        return match ($status) {
            'pending' => 'warning',
            'received' => 'success',
            'discrepancy' => 'danger',
            default => 'gray',
        };
    }

    private static function handoverByLabel(VehicleLoad $record): string
    {
        return $record->handoverUser?->employee?->name
            ?? $record->handoverUser?->name
            ?? '-';
    }

    private static function handoverDifferenceColor(mixed $state): string
    {
        if ($state === null || $state === '') {
            return 'gray';
        }

        $difference = (float) $state;

        return abs($difference) < 0.0005 ? 'success' : 'warning';
    }
}
