<?php

namespace App\Filament\Resources\VehicleExpenses\Schemas;

use App\Models\VehicleExpense;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class VehicleExpenseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                DatePicker::make('expense_date')
                    ->label('تاريخ المصروف')
                    ->default(now())
                    ->required(),

                Select::make('vehicle_id')
                    ->label('السيارة')
                    ->relationship('vehicle', 'plate_number')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->native(false),

                Select::make('warehouse_id')
                    ->label('مستودع السيارة / المستودع')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->native(false)
                    ->helperText('هذا الحقل مهم حتى تدخل المصاريف ضمن إغلاق اليوم الصحيح.'),

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
                    ->label('المندوب')
                    ->relationship('salesRepresentative', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),

                Select::make('expense_type')
                    ->label('نوع المصروف')
                    ->options([
                        'fuel' => 'وقود',
                        'maintenance' => 'صيانة',
                        'washing' => 'غسيل',
                        'fees' => 'رسوم',
                        'parking' => 'موقف',
                        'emergency' => 'طارئ',
                        'other' => 'أخرى',
                    ])
                    ->required()
                    ->native(false),

                TextInput::make('amount')
                    ->label('المبلغ')
                    ->numeric()
                    ->minValue(0)
                    ->step('0.01')
                    ->default(0)
                    ->required(),

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

                FileUpload::make('receipt_path')
                    ->label('صورة الإيصال')
                    ->disk('public')
                    ->directory('vehicle-expense-receipts')
                    ->image()
                    ->imagePreviewHeight('180')
                    ->openable()
                    ->downloadable()
                    ->columnSpanFull(),

                Textarea::make('notes')
                    ->label('ملاحظات')
                    ->columnSpanFull(),

                Textarea::make('rejection_reason')
                    ->label('سبب الرفض')
                    ->disabled()
                    ->dehydrated()
                    ->hidden(fn (?VehicleExpense $record): bool => ! $record?->isRejected())
                    ->columnSpanFull(),
            ]);
    }
}