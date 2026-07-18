<?php

namespace App\Filament\Resources\VehicleLoads\Schemas;

use App\Enums\UserRole;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                Select::make('vehicle_id')
                    ->label('السيارة')
                    ->relationship('vehicle', 'plate_number')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('route_id', null);
                        $set('to_warehouse_id', null);
                    })
                    ->native(false),

                DatePicker::make('load_date')
                    ->label('تاريخ التحميل')
                    ->default(now())
                    ->required(),

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
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('driver_id', null);
                        $set('sales_representative_id', null);
                    })
                    ->native(false),

                Select::make('driver_id')
                    ->label('السائق')
                    ->relationship(
                        'driver',
                        'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query
                            ->where('status', 'active')
                            ->forOperationalRole(UserRole::DRIVER),
                    )
                    ->searchable()
                    ->preload()
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
                    ->native(false),

                Select::make('from_warehouse_id')
                    ->label('من المستودع')
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

                Select::make('to_warehouse_id')
                    ->label('إلى مستودع السيارة')
                    ->relationship(
                        'toWarehouse',
                        'name',
                        modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query
                            ->where('status', 'active')
                            ->where('type', 'vehicle')
                            ->when($get('vehicle_id'), fn (Builder $query, $vehicleId) => $query->where('vehicle_id', $vehicleId)),
                    )
                    ->searchable()
                    ->preload()
                    ->required()
                    ->native(false)
                    ->helperText('يتم عرض مستودعات السيارة المحددة فقط.'),

                Textarea::make('notes')
                    ->label('ملاحظات')
                    ->columnSpanFull(),

                Repeater::make('items')
                    ->label('مواد التحميل')
                    ->relationship('items')
                    ->columns(2)
                    ->columnSpanFull()
                    ->defaultItems(1)
                    ->addActionLabel('إضافة مادة')
                    ->schema([
                        Select::make('product_id')
                            ->label('المنتج')
                            ->relationship('product', 'name_ar')
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

                        TextInput::make('unit_cost')
                            ->label('تكلفة الوحدة عند الاعتماد')
                            ->numeric()
                            ->default(0)
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('تُحتسب تلقائيًا من متوسط تكلفة رصيد المستودع المصدر.'),
                    ]),
            ]);
    }
}
