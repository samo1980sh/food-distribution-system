<?php

namespace App\Filament\Resources\CustomerPayments\Schemas;

use App\Enums\UserRole;
use App\Support\Filament\OperationalFormContext;
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
                    ->disabled(fn (Get $get): bool => filled($get('sales_invoice_id')))
                    ->dehydrated()
                    ->afterStateUpdated(function (mixed $state, Set $set): void {
                        $set('sales_invoice_id', null);
                        self::applyContext($set, OperationalFormContext::forCustomer($state));
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
                            ->when($get('customer_id'), fn (Builder $query, $customerId): Builder => $query->where('customer_id', $customerId)),
                    )
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (mixed $state, Set $set): void {
                        if (blank($state)) {
                            return;
                        }

                        self::applyContext($set, OperationalFormContext::forInvoice($state));
                    })
                    ->native(false)
                    ->helperText('عند اختيار فاتورة يتم تثبيت سياق التحصيل منها تلقائيًا.'),

                Select::make('vehicle_id')
                    ->label('السيارة')
                    ->relationship('vehicle', 'plate_number', modifyQueryUsing: fn (Builder $query): Builder => $query->where('status', 'active'))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->disabled(fn (Get $get): bool => filled($get('sales_invoice_id')))
                    ->dehydrated()
                    ->afterStateUpdated(function (mixed $state, Set $set): void {
                        $set('route_id', null);
                        $set('sales_representative_id', null);
                        $set('warehouse_id', OperationalFormContext::vehicleWarehouseId($state));
                    })
                    ->native(false),

                Select::make('route_id')
                    ->label('خط التوزيع')
                    ->relationship(
                        'route',
                        'name',
                        modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query
                            ->where('status', 'active')
                            ->when($get('vehicle_id'), fn (Builder $query, $vehicleId): Builder => $query->where('vehicle_id', $vehicleId)),
                    )
                    ->searchable()
                    ->preload()
                    ->live()
                    ->disabled(fn (Get $get): bool => filled($get('sales_invoice_id')))
                    ->dehydrated()
                    ->afterStateUpdated(function (mixed $state, Set $set): void {
                        self::applyContext($set, OperationalFormContext::forRoute($state));
                    })
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
                                fn (Builder $query, $vehicleId): Builder => $query
                                    ->where('type', 'vehicle')
                                    ->where('vehicle_id', $vehicleId),
                            ),
                    )
                    ->searchable()
                    ->preload()
                    ->disabled(fn (Get $get): bool => filled($get('sales_invoice_id')))
                    ->dehydrated()
                    ->native(false)
                    ->helperText('مطلوب عند اعتماد تحصيل غير مرتبط بفاتورة حتى يدخل في الإغلاق الصحيح.'),

                Select::make('sales_representative_id')
                    ->label('مندوب التحصيل')
                    ->relationship(
                        'salesRepresentative',
                        'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query
                            ->where('status', 'active')
                            ->forOperationalRole(UserRole::SALES_REPRESENTATIVE),
                    )
                    ->searchable()
                    ->preload()
                    ->disabled(fn (Get $get): bool => filled($get('sales_invoice_id')))
                    ->dehydrated()
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

    /** @param array<string, ?int> $context */
    private static function applyContext(Set $set, array $context): void
    {
        foreach ([
            'customer_id',
            'route_id',
            'vehicle_id',
            'warehouse_id',
            'sales_representative_id',
        ] as $field) {
            if (array_key_exists($field, $context)) {
                $set($field, $context[$field]);
            }
        }
    }
}
