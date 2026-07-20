<?php

namespace App\Filament\Resources\SalesInvoices\Schemas;

use App\Enums\PermissionName;
use App\Enums\UserRole;
use App\Services\Sales\CustomerFinancialService;
use App\Support\Filament\OperationalFormContext;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class SalesInvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('إدخال إداري استثنائي')
                    ->description('فواتير البيع الميدانية تُنشأ من تطبيق المندوب. استخدم هذا المسار فقط عند تعطل التطبيق أو وجود بيع مكتبي استثنائي، مع تسجيل السبب للتدقيق.')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('administrative_reason')
                            ->label('سبب إنشاء الفاتورة من لوحة الإدارة')
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->minLength(10)
                            ->maxLength(2000)
                            ->rows(3)
                            ->disabled(fn (string $operation): bool => $operation !== 'create')
                            ->dehydrated(fn (string $operation): bool => $operation === 'create'),
                    ]),

                Section::make('بيانات العميل والاستحقاق')
                    ->description('ابدأ بالعميل وطريقة الدفع؛ سيقترح النظام السياق التشغيلي وتاريخ الاستحقاق تلقائيًا.')
                    ->icon('heroicon-o-user-circle')
                    ->columns(2)
                    ->schema([
                        Select::make('customer_id')
                                            ->label('العميل')
                                            ->relationship('customer', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query->where('status', 'active'))
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                                                self::applyContext($set, OperationalFormContext::forCustomer($state));
                                                self::applyDueDate($get, $set, $state);
                                            })
                                            ->native(false)
                                            ->helperText('يُحتسب الاستحقاق وحد الائتمان من إعدادات العميل عند اعتماد الفاتورة.'),
                        DatePicker::make('invoice_date')
                                            ->label('تاريخ الفاتورة')
                                            ->default(now())
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                                                self::applyDueDate($get, $set, invoiceDate: $state);
                                            })
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
                                            ->live()
                                            ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                                                self::applyDueDate($get, $set, paymentType: $state);
                                            })
                                            ->native(false),
                        DatePicker::make('due_date')
                                            ->label('تاريخ الاستحقاق')
                                            ->default(now())
                                            ->required()
                                            ->native(false)
                                            ->disabled(fn (Get $get): bool => $get('payment_type') === 'cash')
                                            ->dehydrated()
                                            ->helperText('للفاتورة النقدية يساوي تاريخ الفاتورة، وللآجلة يُقترح من مدة ائتمان العميل.')
                    ]),

                Section::make('السياق التشغيلي')
                    ->description('تأكد من تطابق السيارة والخط ومستودع البيع والمندوب قبل إضافة المواد.')
                    ->icon('heroicon-o-truck')
                    ->columns(2)
                    ->schema([
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
                                            ->native(false)
                    ]),

                Section::make('القيم والضوابط المالية')
                    ->description('تُراجع المبالغ وحد الائتمان مرة أخرى عند اعتماد الفاتورة.')
                    ->icon('heroicon-o-banknotes')
                    ->columns(3)
                    ->collapsible()
                    ->schema([
                        TextInput::make('paid_amount')
                                            ->label('المبلغ المدفوع مع الفاتورة')
                                            ->numeric()
                                            ->minValue(0)
                                            ->default(0),
                        TextInput::make('discount_amount')
                                            ->label('حسم على الفاتورة')
                                            ->numeric()
                                            ->minValue(0)
                                            ->default(0),
                        TextInput::make('tax_amount')
                                            ->label('ضريبة / إضافات')
                                            ->numeric()
                                            ->minValue(0)
                                            ->default(0),
                        Toggle::make('credit_limit_override_requested')
                                            ->label('طلب استثناء من حد الائتمان')
                                            ->helperText('يُراجع حد الائتمان عند الاعتماد، ويُسجل الاستثناء وسببه وهوية المعتمد.')
                                            ->live()
                                            ->visible(fn (): bool => auth()->user()?->can(
                                                PermissionName::SALES_INVOICES_OVERRIDE_CREDIT_LIMIT->value,
                                            ) === true),
                        Textarea::make('credit_limit_override_reason')
                                            ->label('سبب الاستثناء الائتماني')
                                            ->minLength(10)
                                            ->maxLength(2000)
                                            ->required(fn (Get $get): bool => $get('credit_limit_override_requested') === true)
                                            ->visible(fn (Get $get): bool => $get('credit_limit_override_requested') === true)
                                            ->columnSpanFull(),
                        Textarea::make('notes')
                                            ->label('ملاحظات')
                                            ->columnSpanFull()
                    ]),

                Section::make('مواد الفاتورة')
                    ->description('أضف المواد والكميات والأسعار والتشغيلات. لا تعتمد الفاتورة قبل مراجعة جميع السطور.')
                    ->icon('heroicon-o-rectangle-stack')
                    ->columns(1)
                    ->schema([
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
                                                    ->label('تاريخ الصلاحية')
                                                    ->native(false),

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

                                                TextInput::make('discount_amount')
                                                    ->label('حسم المادة')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->default(0),
                                            ])
                    ])
            ]);
    }

    private static function applyDueDate(
        Get $get,
        Set $set,
        mixed $customerId = null,
        mixed $invoiceDate = null,
        mixed $paymentType = null,
    ): void {
        $customerId ??= $get('customer_id');
        $invoiceDate ??= $get('invoice_date');
        $paymentType ??= $get('payment_type');

        if (blank($invoiceDate)) {
            return;
        }

        if ($paymentType === 'cash') {
            $set('due_date', Carbon::parse($invoiceDate)->toDateString());

            return;
        }

        if (! $customerId) {
            return;
        }

        $creditDays = app(CustomerFinancialService::class)
            ->creditDaysForCustomer((int) $customerId);

        $set(
            'due_date',
            Carbon::parse($invoiceDate)
                ->addDays($creditDays)
                ->toDateString(),
        );
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
