<?php

namespace App\Filament\Resources\CustomerPayments\Schemas;

use App\Models\CustomerPayment;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerPaymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('ملخص التحصيل')
                    ->icon('heroicon-o-banknotes')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('payment_number')->label('رقم التحصيل')->copyable(),
                        TextEntry::make('status')
                            ->label('الحالة')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => self::statusLabel($state))
                            ->color(fn (?string $state): string => self::statusColor($state)),
                        TextEntry::make('payment_date')->label('تاريخ التحصيل')->date('Y-m-d'),
                        TextEntry::make('customer.name')->label('العميل'),
                        TextEntry::make('salesInvoice.invoice_number')->label('الفاتورة المرتبطة')->placeholder('-'),
                        TextEntry::make('payment_method')
                            ->label('طريقة الدفع')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => self::methodLabel($state))
                            ->color(fn (?string $state): string => self::methodColor($state)),
                        TextEntry::make('amount')->label('قيمة التحصيل')->money('SYP')->weight('bold'),
                        TextEntry::make('reference_number')->label('المرجع المالي')->placeholder('-'),
                    ]),

                Section::make('أثر الفاتورة')
                    ->icon('heroicon-o-receipt-percent')
                    ->columns(3)
                    ->columnSpanFull()
                    ->visible(fn (?CustomerPayment $record): bool => filled($record?->sales_invoice_id))
                    ->schema([
                        TextEntry::make('salesInvoice.total_amount')->label('إجمالي الفاتورة')->money('SYP'),
                        TextEntry::make('salesInvoice.paid_amount')->label('المدفوع على الفاتورة')->money('SYP'),
                        TextEntry::make('salesInvoice.remaining_amount')
                            ->label('المتبقي على الفاتورة')
                            ->money('SYP')
                            ->weight('bold')
                            ->color(fn ($state): string => (float) $state > 0 ? 'warning' : 'success'),
                    ]),

                Section::make('السياق التشغيلي')
                    ->icon('heroicon-o-map-pin')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('vehicle.plate_number')->label('السيارة')->placeholder('-'),
                        TextEntry::make('route.name')->label('خط التوزيع')->placeholder('-'),
                        TextEntry::make('warehouse.name')->label('المستودع')->placeholder('-'),
                        TextEntry::make('salesRepresentative.name')->label('مندوب التحصيل')->placeholder('-'),
                    ]),

                Section::make('ملاحظات وتدقيق')
                    ->icon('heroicon-o-document-text')
                    ->columns(4)
                    ->columnSpanFull()
                    ->collapsible()
                    ->schema([
                        TextEntry::make('notes')->label('الملاحظات')->placeholder('لا توجد ملاحظات')->columnSpanFull(),
                        TextEntry::make('creator.name')->label('أنشأه')->placeholder('-'),
                        TextEntry::make('confirmer.name')->label('اعتمده')->placeholder('-'),
                        TextEntry::make('created_at')->label('تاريخ الإنشاء')->dateTime('Y-m-d H:i'),
                        TextEntry::make('confirmed_at')->label('تاريخ الاعتماد')->dateTime('Y-m-d H:i')->placeholder('-'),
                    ]),
            ]);
    }

    private static function statusLabel(?string $status): string
    {
        return match ($status) {
            'draft' => 'مسودة',
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

    private static function methodLabel(?string $method): string
    {
        return match ($method) {
            'cash' => 'نقدي',
            'bank_transfer' => 'تحويل بنكي',
            'cheque' => 'شيك',
            'other' => 'أخرى',
            default => $method ?? '-',
        };
    }

    private static function methodColor(?string $method): string
    {
        return match ($method) {
            'cash' => 'success',
            'bank_transfer' => 'info',
            'cheque' => 'warning',
            default => 'gray',
        };
    }
}
