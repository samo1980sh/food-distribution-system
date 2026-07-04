<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('sku')->label('SKU / رمز المنتج')->required()->unique(ignoreRecord: true)->maxLength(255),
                TextInput::make('barcode')->label('الباركود')->unique(ignoreRecord: true)->maxLength(255),
                TextInput::make('name_ar')->label('اسم المنتج بالعربية')->required()->maxLength(255),
                TextInput::make('name_en')->label('اسم المنتج بالإنكليزية')->maxLength(255),
                Select::make('category_id')->label('التصنيف')->relationship('category', 'name_ar')->searchable()->preload()->native(false),
                Select::make('unit_id')->label('الوحدة')->relationship('unit', 'name_ar')->searchable()->preload()->native(false),
                TextInput::make('purchase_price')->label('سعر الشراء')->numeric()->default(0),
                TextInput::make('sale_price')->label('سعر البيع')->numeric()->default(0),
                TextInput::make('wholesale_price')->label('سعر الجملة')->numeric()->default(0),
                TextInput::make('min_stock')->label('حد التنبيه للمخزون')->numeric()->step('0.001')->default(0),
                Toggle::make('has_expiry')->label('يتطلب تاريخ صلاحية')->default(true),

                Select::make('status')
                    ->label('الحالة')
                    ->options([
                        'active' => 'فعال',
                        'inactive' => 'غير فعال',
                    ])
                    ->default('active')
                    ->required()
                    ->native(false),

                Textarea::make('notes')->label('ملاحظات')->columnSpanFull(),
            ]);
    }
}