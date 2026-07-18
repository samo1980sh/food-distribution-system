<?php

namespace App\Filament\Resources\VehicleLoads\Schemas;

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
                        TextEntry::make('vehicle.plate_number')->label('السيارة'),
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
                            ->numeric(decimalPlaces: 3)
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
                                TextEntry::make('quantity')->label('الكمية')->numeric(decimalPlaces: 3),
                                TextEntry::make('unit_cost')->label('تكلفة الوحدة')->money('SYP'),
                                TextEntry::make('total_cost')->label('الإجمالي')->money('SYP')->weight('bold'),
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
}
