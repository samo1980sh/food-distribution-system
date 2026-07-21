<?php

namespace App\Filament\Resources\VehicleLoads\Schemas;

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

class VehicleLoadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('بيانات أمر التحميل')
                    ->description('حدد تاريخ العملية والسيارة والمستودع المصدر قبل إضافة المواد.')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        DatePicker::make('load_date')
                            ->label('تاريخ التحميل')
                            ->default(now())
                            ->required()
                            ->native(false),

                        Select::make('vehicle_id')
                            ->label('السيارة')
                            ->relationship('vehicle', 'plate_number', modifyQueryUsing: fn (Builder $query): Builder => $query->where('status', 'active'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (mixed $state, Set $set): void {
                                $set('route_id', null);
                                $set('driver_id', null);
                                $set('sales_representative_id', null);
                                $set('to_warehouse_id', OperationalFormContext::vehicleWarehouseId($state));
                            })
                            ->native(false),

                        Select::make('from_warehouse_id')
                            ->label('المستودع المصدر')
                            ->relationship(
                                'fromWarehouse',
                                'name',
                                modifyQueryUsing: fn (Builder $query): Builder => $query
                                    ->where('status', 'active')
                                    ->where('type', '!=', 'vehicle'),
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),
                    ]),

                Section::make('السياق التشغيلي')
                    ->description('يربط أمر التحميل بخط التوزيع وفريقه ومستودع السيارة الصحيح.')
                    ->icon('heroicon-o-map-pin')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
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
                                $context = OperationalFormContext::forRoute($state);
                                $set('vehicle_id', $context['vehicle_id']);
                                $set('to_warehouse_id', $context['warehouse_id']);
                                $set('driver_id', $context['driver_id']);
                                $set('sales_representative_id', $context['sales_representative_id']);
                            })
                            ->native(false),

                        Select::make('to_warehouse_id')
                            ->label('مستودع السيارة')
                            ->relationship(
                                'toWarehouse',
                                'name',
                                modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query
                                    ->where('status', 'active')
                                    ->where('type', 'vehicle')
                                    ->when($get('vehicle_id'), fn (Builder $query, $vehicleId): Builder => $query->where('vehicle_id', $vehicleId)),
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false)
                            ->helperText('يتم تثبيت مستودع السيارة المحددة تلقائيًا.'),

                        Select::make('driver_id')
                            ->label('السائق')
                            ->relationship(
                                'driver',
                                'name',
                                modifyQueryUsing: function (Builder $query, Get $get): Builder {
                                    $query
                                        ->where('status', 'active')
                                        ->forOperationalRole(UserRole::DRIVER);

                                    $driverId = OperationalFormContext::forRoute($get('route_id'))['driver_id'];

                                    return $driverId === null ? $query : $query->whereKey($driverId);
                                },
                            )
                            ->searchable()
                            ->preload()
                            ->native(false),

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
                    ]),

                Section::make('مواد التحميل')
                    ->description('اختر المنتج والكمية فقط. عند الاعتماد يختار النظام تلقائيًا الدفعة الأقرب انتهاءً ويقسم الكمية على أكثر من دفعة عند الحاجة.')
                    ->icon('heroicon-o-cube')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('items')
                            ->label('')
                            ->relationship('items')
                            ->columns(4)
                            ->columnSpanFull()
                            ->defaultItems(1)
                            ->minItems(1)
                            ->addActionLabel('إضافة مادة إلى أمر التحميل')
                            ->schema([
                                Select::make('product_id')
                                    ->label('المنتج')
                                    ->relationship('product', 'name_ar', modifyQueryUsing: fn (Builder $query): Builder => $query->where('status', 'active'))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->native(false)
                                    ->helperText('لا حاجة لإدخال رقم التشغيلة أو تاريخ الصلاحية؛ تتم إدارتهما تلقائيًا.')
                                    ->columnSpan(2),

                                TextInput::make('quantity')
                                    ->label('الكمية')
                                    ->numeric()
                                    ->minValue(0.001)
                                    ->step('0.001')
                                    ->required(),

                                TextInput::make('unit_cost')
                                    ->label('تكلفة الوحدة')
                                    ->numeric()
                                    ->default(0)
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->helperText('تُحتسب تلقائيًا عند الاعتماد.'),
                            ]),
                    ]),

                Section::make('ملاحظات')
                    ->icon('heroicon-o-document-text')
                    ->columnSpanFull()
                    ->collapsible()
                    ->schema([
                        Textarea::make('notes')
                            ->label('ملاحظات أمر التحميل')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
