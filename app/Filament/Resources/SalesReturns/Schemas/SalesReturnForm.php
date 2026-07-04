<?php

namespace App\Filament\Resources\SalesReturns\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SalesReturnForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('customer_id')
                    ->label('العميل')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->native(false),

                DatePicker::make('return_date')
                    ->label('تاريخ المرتجع')
                    ->default(now())
                    ->required(),

                Select::make('sales_invoice_id')
                    ->label('الفاتورة الأصلية')
                    ->relationship('salesInvoice', 'invoice_number')
                    ->searchable()
                    ->preload()
                    ->native(false),

                Select::make('vehicle_id')
                    ->label('السيارة')
                    ->relationship('vehicle', 'plate_number')
                    ->searchable()
                    ->preload()
                    ->native(false),

                Select::make('route_id')
                    ->label('خط التوزيع')
                    ->relationship('route', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),

                Select::make('warehouse_id')
                    ->label('المستودع الذي سيستلم المرتجع')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->native(false)
                    ->helperText('غالبًا يكون مستودع السيارة إذا عاد المنتج مع المندوب.'),

                Select::make('sales_representative_id')
                    ->label('مندوب المبيعات')
                    ->relationship('salesRepresentative', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),

                Select::make('return_reason')
                    ->label('سبب المرتجع')
                    ->options([
                        'expired' => 'منتهي الصلاحية',
                        'damaged' => 'تالف',
                        'customer_refused' => 'رفض العميل',
                        'wrong_item' => 'مادة خاطئة',
                        'other' => 'أخرى',
                    ])
                    ->native(false),

                TextInput::make('discount_amount')
                    ->label('حسم على المرتجع')
                    ->numeric()
                    ->default(0),

                Textarea::make('notes')
                    ->label('ملاحظات')
                    ->columnSpanFull(),

                Repeater::make('items')
                    ->label('مواد المرتجع')
                    ->relationship('items')
                    ->columns(2)
                    ->columnSpanFull()
                    ->defaultItems(1)
                    ->addActionLabel('إضافة مادة')
                    ->schema([
                        Select::make('product_id')
                            ->label('المنتج')
                            ->relationship('product', 'name_ar')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false)
                            ->columnSpanFull(),

                        TextInput::make('batch_number')
                            ->label('رقم التشغيلة')
                            ->maxLength(255),

                        DatePicker::make('expiry_date')
                            ->label('تاريخ الصلاحية'),

                        TextInput::make('quantity')
                            ->label('الكمية')
                            ->numeric()
                            ->minValue(0.001)
                            ->step('0.001')
                            ->required(),

                        TextInput::make('unit_price')
                            ->label('سعر الوحدة')
                            ->numeric()
                            ->default(0)
                            ->required(),
                    ]),
            ]);
    }
}