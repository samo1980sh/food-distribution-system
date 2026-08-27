<?php

namespace App\Filament\Resources\StockMovements\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class StockMovementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                DatePicker::make('movement_date')
                    ->label('تاريخ الحركة')
                    ->default(now())
                    ->required()
                    ->native(false)
                    ->helperText('يُستخدم هذا التاريخ في دفتر المخزون والإغلاق اليومي.'),

                Select::make('movement_type')
                    ->label('نوع الحركة')
                    ->options([
                        'opening_balance' => 'رصيد افتتاحي',
                        'manual_out' => 'إخراج يدوي / تسوية سالبة',
                    ])
                    ->default('opening_balance')
                    ->required()
                    ->live()
                    ->native(false),

                Select::make('product_id')
                    ->label('المنتج')
                    ->relationship('product', 'name_ar')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->native(false),

                Select::make('from_warehouse_id')
                    ->label('من المستودع')
                    ->relationship('fromWarehouse', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->hidden(fn ($get): bool => $get('movement_type') === 'opening_balance')
                    ->required(fn ($get): bool => $get('movement_type') === 'manual_out'),

                Select::make('to_warehouse_id')
                    ->label('إلى المستودع')
                    ->relationship('toWarehouse', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->hidden(fn ($get): bool => $get('movement_type') === 'manual_out')
                    ->required(fn ($get): bool => $get('movement_type') === 'opening_balance'),

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
                    ->minValue(0)
                    ->hidden(fn ($get): bool => $get('movement_type') !== 'opening_balance')
                    ->required(fn ($get): bool => $get('movement_type') === 'opening_balance')
                    ->helperText('يُستخدم للرصيد الافتتاحي فقط. التوريد والتحويل لهما إجراءات مستقلة وواضحة أعلى الصفحة.'),

                Textarea::make('notes')
                    ->label('سبب الحركة الإدارية')
                    ->required()
                    ->minLength(10)
                    ->maxLength(2000)
                    ->helperText('سبب إلزامي للتدقيق. استخدم إجراءات التوريد والتحويل المخصصة، ولا تستخدم هذه التسوية بدل العمليات التشغيلية.')
                    ->columnSpanFull(),
            ]);
    }
}
