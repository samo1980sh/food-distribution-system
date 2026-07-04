<?php

namespace App\Filament\Resources\CustomerPayments\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class CustomerPaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('customer_id')
                    ->label('العميل')
                    ->relationship('customer', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query->where('status', 'active'))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('sales_invoice_id', null);
                    })
                    ->native(false),

                DatePicker::make('payment_date')
                    ->label('تاريخ التحصيل')
                    ->default(now())
                    ->required(),

                Select::make('sales_invoice_id')
                    ->label('الفاتورة')
                    ->relationship(
                        'salesInvoice',
                        'invoice_number',
                        modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query
                            ->where('status', 'confirmed')
                            ->where('remaining_amount', '>', 0)
                            ->when($get('customer_id'), fn (Builder $query, $customerId) => $query->where('customer_id', $customerId)),
                    )
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->helperText('تظهر الفواتير المعتمدة وغير المسددة للعميل المحدد فقط.'),

                Select::make('vehicle_id')
                    ->label('السيارة')
                    ->relationship('vehicle', 'plate_number', modifyQueryUsing: fn (Builder $query): Builder => $query->where('status', 'active'))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('route_id', null);
                        $set('warehouse_id', null);
                    })
                    ->native(false)
                    ->helperText('اختياري. عند ربط التحصيل بفاتورة سيتم ملؤه عند الاعتماد من الفاتورة إذا ترك فارغاً.'),

                Select::make('route_id')
                    ->label('خط التوزيع')
                    ->relationship(
                        'route',
                        'name',
                        modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query
                            ->when($get('vehicle_id'), fn (Builder $query, $vehicleId) => $query->where('vehicle_id', $vehicleId))
                            ->where('status', 'active'),
                    )
                    ->searchable()
                    ->preload()
                    ->native(false),

                Select::make('warehouse_id')
                    ->label('المستودع')
                    ->relationship(
                        'warehouse',
                        'name',
                        modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query
                            ->where('status', 'active')
                            ->when(
                                $get('vehicle_id'),
                                fn (Builder $query, $vehicleId) => $query->where('type', 'vehicle')->where('vehicle_id', $vehicleId),
                            ),
                    )
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->helperText('مطلوب عند اعتماد تحصيل غير مرتبط بفاتورة حتى يدخل في الإغلاق الصحيح.'),

                Select::make('sales_representative_id')
                    ->label('مندوب التحصيل')
                    ->relationship(
                        'salesRepresentative',
                        'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query
                            ->where('status', 'active')
                            ->where('type', 'sales_representative'),
                    )
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
