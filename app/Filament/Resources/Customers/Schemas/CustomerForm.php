<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('code')->label('رمز العميل')->required()->unique(ignoreRecord: true)->maxLength(255),
                TextInput::make('name')->label('اسم العميل / المحل')->required()->maxLength(255),
                TextInput::make('owner_name')->label('اسم صاحب المحل')->maxLength(255),

                Select::make('customer_type')
                    ->label('نوع العميل')
                    ->options([
                        'grocery' => 'بقالية',
                        'supermarket' => 'سوبر ماركت',
                        'restaurant' => 'مطعم',
                        'wholesaler' => 'موزع / جملة',
                        'mini_market' => 'ميني ماركت',
                        'other' => 'أخرى',
                    ])
                    ->default('grocery')
                    ->required()
                    ->native(false),

                TextInput::make('phone')->label('الهاتف')->tel()->maxLength(255),
                TextInput::make('mobile')->label('الموبايل')->tel()->maxLength(255),
                Select::make('area_id')->label('المنطقة')->relationship('area', 'name_ar')->searchable()->preload()->native(false),
                Select::make('route_id')->label('خط التوزيع')->relationship('route', 'name')->searchable()->preload()->native(false),
                TextInput::make('address')->label('العنوان')->maxLength(255)->columnSpanFull(),
                TextInput::make('latitude')->label('خط العرض')->numeric()->step('0.0000001'),
                TextInput::make('longitude')->label('خط الطول')->numeric()->step('0.0000001'),
                TextInput::make('credit_limit')->label('حد الائتمان')->numeric()->default(0),

                Select::make('payment_type')
                    ->label('طريقة الدفع المعتادة')
                    ->options([
                        'cash' => 'نقدي',
                        'credit' => 'آجل',
                        'weekly' => 'أسبوعي',
                        'monthly' => 'شهري',
                    ])
                    ->default('cash')
                    ->required()
                    ->native(false),

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