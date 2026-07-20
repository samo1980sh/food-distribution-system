<?php

namespace App\Filament\Resources\SalesReturns\Schemas;

use App\Enums\OperationSource;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SalesReturnInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('ملخص المرتجع')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('return_number')->label('رقم المرتجع')->copyable(),
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
                        TextEntry::make('return_date')->label('تاريخ المرتجع')->date('Y-m-d'),
                        TextEntry::make('return_reason')
                            ->label('سبب المرتجع')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => self::reasonLabel($state))
                            ->color(fn (?string $state): string => self::reasonColor($state)),
                        TextEntry::make('customer.name')->label('العميل'),
                        TextEntry::make('salesInvoice.invoice_number')->label('الفاتورة الأصلية')->placeholder('-'),
                        TextEntry::make('vehicle.plate_number')->label('السيارة')->placeholder('-'),
                        TextEntry::make('route.name')->label('خط التوزيع')->placeholder('-'),
                        TextEntry::make('warehouse.name')->label('مستودع الاستلام'),
                        TextEntry::make('salesRepresentative.name')->label('مندوب المبيعات')->placeholder('-'),
                        TextEntry::make('creator.name')->label('أنشأه')->placeholder('-'),
                        TextEntry::make('confirmer.name')->label('اعتمده')->placeholder('-'),
                    ]),

                Section::make('الملخص المالي')
                    ->icon('heroicon-o-banknotes')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('subtotal')->label('المجموع')->money('SYP'),
                        TextEntry::make('discount_amount')->label('الحسم')->money('SYP'),
                        TextEntry::make('total_amount')->label('صافي المرتجع')->money('SYP')->weight('bold'),
                    ]),

                Section::make('مواد المرتجع')
                    ->icon('heroicon-o-rectangle-stack')
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->columns(6)
                            ->schema([
                                TextEntry::make('product.name_ar')->label('المنتج')->columnSpan(2),
                                TextEntry::make('quantity')->label('الكمية')->numeric(decimalPlaces: 3),
                                TextEntry::make('unit_price')->label('سعر الوحدة')->money('SYP'),
                                TextEntry::make('line_total')->label('الإجمالي')->money('SYP')->weight('bold'),
                                TextEntry::make('batch_number')->label('التشغيلة')->placeholder('-'),
                                TextEntry::make('expiry_date')->label('الصلاحية')->date('Y-m-d')->placeholder('-'),
                            ]),
                    ]),

                Section::make('ملاحظات وتدقيق')
                    ->icon('heroicon-o-document-text')
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsible()
                    ->schema([
                        TextEntry::make('notes')->label('الملاحظات')->placeholder('لا توجد ملاحظات')->columnSpanFull(),
                        TextEntry::make('administrative_reason')
                            ->label('بيان / سبب الإدخال الإداري')
                            ->placeholder('لا يوجد - العملية واردة من التطبيق أو من بيانات سابقة')
                            ->columnSpanFull(),
                        TextEntry::make('created_at')->label('تاريخ الإنشاء')->dateTime('Y-m-d H:i'),
                        TextEntry::make('confirmed_at')->label('تاريخ الاعتماد')->dateTime('Y-m-d H:i')->placeholder('-'),
                    ]),
            ]);
    }

    private static function statusLabel(?string $status): string
    {
        return match ($status) {
            'draft' => 'بانتظار الاعتماد',
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

    private static function reasonLabel(?string $reason): string
    {
        return match ($reason) {
            'expired' => 'منتهي الصلاحية',
            'damaged' => 'تالف',
            'customer_refused' => 'رفض العميل',
            'wrong_item' => 'مادة خاطئة',
            'other' => 'أخرى',
            default => $reason ?? '-',
        };
    }

    private static function reasonColor(?string $reason): string
    {
        return match ($reason) {
            'expired' => 'danger',
            'damaged' => 'warning',
            'wrong_item' => 'info',
            default => 'gray',
        };
    }
}
