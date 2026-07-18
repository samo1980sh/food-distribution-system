<?php

namespace App\Filament\Resources\VehicleExpenses\Schemas;

use App\Models\VehicleExpense;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VehicleExpenseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('ملخص المصروف')
                    ->icon('heroicon-o-banknotes')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('expense_number')->label('رقم المصروف')->copyable(),
                        TextEntry::make('status')
                            ->label('الحالة')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => self::statusLabel($state))
                            ->color(fn (?string $state): string => self::statusColor($state)),
                        TextEntry::make('expense_date')->label('تاريخ المصروف')->date('Y-m-d'),
                        TextEntry::make('expense_type')
                            ->label('نوع المصروف')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => self::typeLabel($state)),
                        TextEntry::make('amount')->label('المبلغ')->money('SYP')->weight('bold'),
                        TextEntry::make('payment_method')
                            ->label('طريقة الدفع')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => self::paymentLabel($state)),
                        TextEntry::make('receipt_path')
                            ->label('الإيصال')
                            ->placeholder('لا يوجد إيصال مرفق')
                            ->formatStateUsing(fn (?string $state): string => filled($state) ? 'فتح الإيصال المرفق' : 'لا يوجد إيصال مرفق')
                            ->url(fn (?string $state): ?string => filled($state) ? asset('storage/'.$state) : null)
                            ->openUrlInNewTab(),
                    ]),

                Section::make('السياق التشغيلي')
                    ->icon('heroicon-o-map-pin')
                    ->columns(5)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('vehicle.plate_number')->label('السيارة'),
                        TextEntry::make('warehouse.name')->label('مستودع السيارة'),
                        TextEntry::make('route.name')->label('خط التوزيع')->placeholder('-'),
                        TextEntry::make('driver.name')->label('السائق')->placeholder('-'),
                        TextEntry::make('salesRepresentative.name')->label('مندوب المبيعات')->placeholder('-'),
                    ]),

                Section::make('سجل الاعتماد والمراجعة')
                    ->icon('heroicon-o-shield-check')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('createdBy.name')->label('أنشأه')->placeholder('-'),
                        TextEntry::make('approvedBy.name')->label('اعتمده')->placeholder('-'),
                        TextEntry::make('approved_at')->label('تاريخ الاعتماد')->dateTime('Y-m-d H:i')->placeholder('-'),
                        TextEntry::make('rejectedBy.name')->label('رفضه')->placeholder('-'),
                        TextEntry::make('rejected_at')->label('تاريخ الرفض')->dateTime('Y-m-d H:i')->placeholder('-'),
                        TextEntry::make('rejection_reason')
                            ->label('سبب الرفض')
                            ->placeholder('-')
                            ->visible(fn (?VehicleExpense $record): bool => $record?->isRejected() === true)
                            ->columnSpanFull(),
                    ]),

                Section::make('ملاحظات')
                    ->icon('heroicon-o-document-text')
                    ->columnSpanFull()
                    ->collapsible()
                    ->schema([
                        TextEntry::make('notes')->label('الملاحظات')->placeholder('لا توجد ملاحظات')->columnSpanFull(),
                    ]),
            ]);
    }

    private static function statusLabel(?string $status): string
    {
        return match ($status) {
            'pending' => 'قيد المراجعة',
            'approved' => 'معتمد',
            'rejected' => 'مرفوض',
            default => $status ?? '-',
        };
    }

    private static function statusColor(?string $status): string
    {
        return match ($status) {
            'pending' => 'warning',
            'approved' => 'success',
            'rejected' => 'danger',
            default => 'gray',
        };
    }

    private static function typeLabel(?string $type): string
    {
        return match ($type) {
            'fuel' => 'وقود',
            'maintenance' => 'صيانة',
            'washing' => 'غسيل',
            'fees' => 'رسوم',
            'parking' => 'موقف',
            'emergency' => 'طارئ',
            'other' => 'أخرى',
            default => $type ?? '-',
        };
    }

    private static function paymentLabel(?string $method): string
    {
        return match ($method) {
            'cash' => 'نقدي',
            'bank_transfer' => 'تحويل بنكي',
            'cheque' => 'شيك',
            'other' => 'أخرى',
            default => $method ?? '-',
        };
    }
}
