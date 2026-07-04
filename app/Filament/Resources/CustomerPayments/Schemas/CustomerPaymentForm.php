<?php

namespace App\Filament\Resources\CustomerPayments\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CustomerPaymentForm
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

                DatePicker::make('payment_date')
                    ->label('تاريخ التحصيل')
                    ->default(now())
                    ->required(),

                Select::make('sales_invoice_id')
                    ->label('الفاتورة')
                    ->relationship('salesInvoice', 'invoice_number')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->helperText('اختياري. عند اختيار فاتورة معتمدة سيتم تحديث المدفوع والمتبقي عند اعتماد التحصيل.'),

                Select::make('sales_representative_id')
                    ->label('مندوب التحصيل')
                    ->relationship('salesRepresentative', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),

                Select::make('payment_method')
                    ->label('طريقة الدفع')
                    ->options([
                        'cash' => 'نقدي',
                        'bank_transfer' => 'تحويل بنكي',
                        'cheque' => 'شيك',
                        'other' => 'أخرى',
                    ])
                    ->default('cash')
                    ->required()
                    ->native(false),

                TextInput::make('amount')
                    ->label('المبلغ')
                    ->numeric()
                    ->minValue(0.01)
                    ->required(),

                TextInput::make('reference_number')
                    ->label('رقم المرجع / الشيك')
                    ->maxLength(255),

                Textarea::make('notes')
                    ->label('ملاحظات')
                    ->columnSpanFull(),
            ]);
    }
}