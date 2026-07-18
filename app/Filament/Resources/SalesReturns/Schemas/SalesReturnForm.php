<?php

namespace App\Filament\Resources\SalesReturns\Schemas;

use App\Enums\UserRole;
use App\Support\Filament\OperationalFormContext;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class SalesReturnForm
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

                DatePicker::make('return_date')
                    ->label('تاريخ المرتجع')
                    ->default(now())
                    ->required(),

                Select::make('sales_invoice_id')
                    ->label('الفاتورة الأصلية')
                    ->relationship(
                        'salesInvoice',
                        'invoice_number',
                        modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query
                            ->where('status', 'confirmed')
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
                    ->helperText('عند اختيار فاتورة يتم تثبيت العميل والسيارة والخط والمستودع والمندوب منها.'),

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
                    ->label('المستودع الذي سيستلم المرتجع')
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
                    ->required()
                    ->disabled(fn (Get $get): bool => filled($get('sales_invoice_id')))
                    ->dehydrated()
                    ->native(false),

                Select::make('sales_representative_id')
                    ->label('مندوب المبيعات')
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
                            ->relationship('product', 'name_ar', modifyQueryUsing: fn (Builder $query): Builder => $query->where('status', 'active'))
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

                        TextInput::make('unit_cost')
                            ->label('تكلفة الوحدة المخزنية')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->helperText('للمرتجع المرتبط بفاتورة تُستعاد التكلفة تلقائيًا من الفاتورة الأصلية.'),
                    ]),
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
