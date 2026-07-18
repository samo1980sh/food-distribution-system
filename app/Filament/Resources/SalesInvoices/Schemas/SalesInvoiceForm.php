<?php

namespace App\Filament\Resources\SalesInvoices\Schemas;

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

class SalesInvoiceForm
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
                    ->afterStateUpdated(function (mixed $state, Set $set): void {
                        self::applyContext($set, OperationalFormContext::forCustomer($state));
                    })
                    ->native(false)
                    ->helperText('عند اختيار العميل يتم تعبئة الخط والسيارة والمستودع والمندوب من تكليف العميل.'),

                DatePicker::make('invoice_date')
                    ->label('تاريخ الفاتورة')
                    ->default(now())
                    ->required(),

                Select::make('vehicle_id')
                    ->label('السيارة')
                    ->relationship('vehicle', 'plate_number', modifyQueryUsing: fn (Builder $query): Builder => $query->where('status', 'active'))
                    ->searchable()
                    ->preload()
                    ->live()
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
                    ->afterStateUpdated(function (mixed $state, Set $set): void {
                        self::applyContext($set, OperationalFormContext::forRoute($state));
                    })
                    ->native(false),

                Select::make('warehouse_id')
                    ->label('مستودع البيع / السيارة')
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
                    ->native(false)
                    ->helperText('عند اختيار سيارة، يتم تثبيت مستودع السيارة المطابق.'),

                Select::make('sales_representative_id')
                    ->label('مندوب المبيعات')
                    ->relationship(
                        'salesRepresentative',
                        'name',
                        modifyQueryUsing: function (Builder $query, Get $get): Builder {
                            $query
                                ->where('status', 'active')
                                ->forOperationalRole(UserRole::SALES_REPRESENTATIVE);

                            $representativeId = OperationalFormContext::forRoute(
                                $get('route_id'),
                            )['sales_representative_id'];

                            return $representativeId === null
                                ? $query
                                : $query->whereKey($representativeId);
                        },
                    )
                    ->searchable()
                    ->preload()
                    ->native(false),

                Select::make('payment_type')
                    ->label('طريقة الدفع')
                    ->options([
                        'cash' => 'نقدي',
                        'credit' => 'آجل',
                        'partial' => 'دفعة جزئية',
                    ])
                    ->default('cash')
                    ->required()
                    ->native(false),

                TextInput::make('paid_amount')
                    ->label('المبلغ المدفوع')
                    ->numeric()
                    ->default(0),

                TextInput::make('discount_amount')
                    ->label('حسم على الفاتورة')
                    ->numeric()
                    ->default(0),

                TextInput::make('tax_amount')
                    ->label('ضريبة / إضافات')
                    ->numeric()
                    ->default(0),

                Textarea::make('notes')
                    ->label('ملاحظات')
                    ->columnSpanFull(),

                Repeater::make('items')
                    ->label('مواد الفاتورة')
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

                        TextInput::make('discount_amount')
                            ->label('حسم المادة')
                            ->numeric()
                            ->default(0),
                    ]),
            ]);
    }

    /** @param array<string, ?int> $context */
    private static function applyContext(Set $set, array $context): void
    {
        foreach ($context as $field => $value) {
            if (in_array($field, [
                'route_id',
                'vehicle_id',
                'warehouse_id',
                'sales_representative_id',
            ], true)) {
                $set($field, $value);
            }
        }
    }
}
