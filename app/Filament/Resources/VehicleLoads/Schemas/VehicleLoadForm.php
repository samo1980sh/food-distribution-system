<?php

namespace App\Filament\Resources\VehicleLoads\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class VehicleLoadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('vehicle_id')
                    ->label('السيارة')
                    ->relationship('vehicle', 'plate_number')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->native(false),

                DatePicker::make('load_date')
                    ->label('تاريخ التحميل')
                    ->default(now())
                    ->required(),

                Select::make('route_id')
                    ->label('خط التوزيع')
                    ->relationship('route', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),

                Select::make('driver_id')
                    ->label('السائق')
                    ->relationship('driver', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),

                Select::make('sales_representative_id')
                    ->label('مندوب المبيعات')
                    ->relationship('salesRepresentative', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),

                Select::make('from_warehouse_id')
                    ->label('من المستودع')
                    ->relationship('fromWarehouse', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->native(false),

                Select::make('to_warehouse_id')
                    ->label('إلى مستودع السيارة')
                    ->relationship('toWarehouse', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->native(false)
                    ->helperText('اختر مستودع السيارة المرتبط بالسيارة المحددة.'),

                Textarea::make('notes')
                    ->label('ملاحظات')
                    ->columnSpanFull(),

                Repeater::make('items')
                    ->label('مواد التحميل')
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

                        TextInput::make('unit_cost')
                            ->label('تكلفة الوحدة')
                            ->numeric()
                            ->default(0),
                    ]),
            ]);
    }
}