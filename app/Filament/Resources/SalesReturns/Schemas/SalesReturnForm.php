<?php

namespace App\Filament\Resources\SalesReturns\Schemas;

use App\Enums\UserRole;
use App\Support\Filament\OperationalFormContext;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
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
                Section::make('إدخال إداري استثنائي')
                    ->description('طلبات المرتجع الميدانية تُسجل من تطبيق المندوب. استخدم هذا المسار فقط للحالات الإدارية الاستثنائية، مع توثيق السبب.')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('administrative_reason')
                            ->label('سبب إنشاء المرتجع من لوحة الإدارة')
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->minLength(10)
                            ->maxLength(2000)
                            ->rows(3)
                            ->disabled(fn (string $operation): bool => $operation !== 'create')
                            ->dehydrated(fn (string $operation): bool => $operation === 'create'),
                    ]),

                Section::make('المرجع وسبب المرتجع')
                    ->description('اربط المرتجع بالفاتورة الأصلية كلما أمكن لضمان مطابقة العميل والمواد والأثر المالي.')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->columns(2)
                    ->schema([
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
                            ->required()
                            ->native(false),

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
                            ->helperText('اختيار الفاتورة يثبت العميل والسيارة والخط والمستودع والمندوب منها.'),

                        Select::make('return_reason')
                            ->label('سبب المرتجع')
                            ->options([
                                'expired' => 'منتهي الصلاحية',
                                'damaged' => 'تالف',
                                'customer_refused' => 'رفض العميل',
                                'wrong_item' => 'مادة خاطئة',
                                'other' => 'أخرى',
                            ])
                            ->required()
                            ->native(false),
                    ]),

                Section::make('السياق التشغيلي')
                    ->description('يصبح السياق للقراءة فقط عند ربط المرتجع بفاتورة أصلية، منعًا لتغيير مساره التاريخي.')
                    ->icon('heroicon-o-truck')
                    ->columns(2)
                    ->schema([
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
                    ]),

                Section::make('مواد المرتجع')
                    ->description('أدخل فقط المواد والكميات المستلمة فعليًا. يتحقق النظام من عدم تجاوز كميات الفاتورة الأصلية عند الاعتماد.')
                    ->icon('heroicon-o-rectangle-stack')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('items')
                            ->label('')
                            ->relationship('items')
                            ->columns(3)
                            ->defaultItems(1)
                            ->minItems(1)
                            ->addActionLabel('إضافة مادة مرتجعة')
                            ->schema([
                                Select::make('product_id')
                                    ->label('المنتج')
                                    ->relationship('product', 'name_ar', modifyQueryUsing: fn (Builder $query): Builder => $query->where('status', 'active'))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->native(false)
                                    ->columnSpan(2),

                                TextInput::make('quantity')
                                    ->label('الكمية')
                                    ->numeric()
                                    ->minValue(0.001)
                                    ->step('0.001')
                                    ->required(),

                                TextInput::make('unit_price')
                                    ->label('سعر الوحدة')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0)
                                    ->required(),

                                TextInput::make('unit_cost')
                                    ->label('تكلفة الوحدة المخزنية')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0)
                                    ->helperText('للمرتجع المرتبط بفاتورة تُستعاد التكلفة تلقائيًا من الفاتورة الأصلية.'),

                                TextInput::make('batch_number')
                                    ->label('رقم التشغيلة')
                                    ->maxLength(255),

                                DatePicker::make('expiry_date')
                                    ->label('تاريخ الصلاحية')
                                    ->native(false),
                            ]),
                    ]),

                Section::make('الخصم والملاحظات')
                    ->description('يُعاد احتساب إجمالي المرتجع تلقائيًا بعد حفظ المواد.')
                    ->icon('heroicon-o-document-text')
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsible()
                    ->schema([
                        TextInput::make('discount_amount')
                            ->label('حسم على المرتجع')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),

                        Textarea::make('notes')
                            ->label('ملاحظات')
                            ->rows(4)
                            ->columnSpanFull(),
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
