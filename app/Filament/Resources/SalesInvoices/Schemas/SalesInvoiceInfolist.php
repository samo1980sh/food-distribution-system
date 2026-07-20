<?php

namespace App\Filament\Resources\SalesInvoices\Schemas;

use App\Enums\OperationSource;
use App\Models\SalesInvoice;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SalesInvoiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('ملخص الفاتورة')
                    ->icon('heroicon-o-receipt-percent')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('invoice_number')->label('رقم الفاتورة')->copyable(),
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
                        TextEntry::make('invoice_date')->label('تاريخ الفاتورة')->date('Y-m-d'),
                        TextEntry::make('due_date')->label('تاريخ الاستحقاق')->date('Y-m-d'),
                        TextEntry::make('customer.name')->label('العميل'),
                        TextEntry::make('payment_type')
                            ->label('طريقة الدفع')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'cash' => 'نقدي',
                                'credit' => 'آجل',
                                'partial' => 'جزئي',
                                default => $state ?? '-',
                            }),
                        TextEntry::make('vehicle.plate_number')->label('السيارة')->placeholder('-'),
                        TextEntry::make('route.name')->label('خط التوزيع')->placeholder('-'),
                        TextEntry::make('warehouse.name')->label('مستودع البيع'),
                        TextEntry::make('salesRepresentative.name')->label('مندوب المبيعات')->placeholder('-'),
                        TextEntry::make('creator.name')->label('أنشأها')->placeholder('-'),
                        TextEntry::make('confirmer.name')->label('اعتمدها')->placeholder('-'),
                    ]),

                Section::make('الملخص المالي')
                    ->icon('heroicon-o-banknotes')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('subtotal')->label('المجموع')->money('SYP'),
                        TextEntry::make('discount_amount')->label('الحسم')->money('SYP'),
                        TextEntry::make('tax_amount')->label('الضريبة / الإضافات')->money('SYP'),
                        TextEntry::make('total_amount')->label('الإجمالي')->money('SYP')->weight('bold'),
                        TextEntry::make('paid_amount')->label('المدفوع')->money('SYP'),
                        TextEntry::make('remaining_amount')
                            ->label('المتبقي')
                            ->money('SYP')
                            ->color(fn ($state): string => (float) $state > 0 ? 'warning' : 'success')
                            ->weight('bold'),
                        TextEntry::make('credit_limit_overridden')
                            ->label('استثناء حد الائتمان')
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'تم التجاوز' : 'لا')
                            ->color(fn (bool $state): string => $state ? 'danger' : 'gray'),
                        TextEntry::make('credit_limit_override_reason')
                            ->label('سبب الاستثناء')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),

                Section::make('مواد الفاتورة')
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
                                TextEntry::make('discount_amount')->label('الحسم')->money('SYP'),
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
            'confirmed' => 'معتمدة',
            'cancelled' => 'ملغاة',
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
