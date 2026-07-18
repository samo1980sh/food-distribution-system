<?php

namespace App\Filament\Resources\DailyClosings\Schemas;

use App\Enums\UserRole;
use App\Models\DailyClosing;
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

class DailyClosingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('نطاق الإغلاق')
                    ->description('حدد التاريخ ومستودع السيارة أو المستودع المراد مطابقته. لا يمكن وجود أكثر من إغلاق فعّال لنفس التاريخ والمستودع.')
                    ->icon('heroicon-o-calendar-days')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        DatePicker::make('closing_date')
                            ->label('تاريخ الإغلاق')
                            ->default(now())
                            ->required()
                            ->native(false),

                        Select::make('vehicle_id')
                            ->label('السيارة')
                            ->relationship(
                                'vehicle',
                                'plate_number',
                                modifyQueryUsing: fn (Builder $query): Builder => $query->where('status', 'active'),
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (mixed $state, Set $set): void {
                                $set('route_id', null);
                                $set('sales_representative_id', null);
                                $set('warehouse_id', OperationalFormContext::vehicleWarehouseId($state));
                            })
                            ->native(false),

                        Select::make('warehouse_id')
                            ->label('مستودع المطابقة')
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
                            ->helperText('عند تحديد سيارة، يجب أن يكون المستودع تابعًا لها.'),
                    ]),

                Section::make('السياق التشغيلي')
                    ->description('يساعد ربط الإغلاق بخط التوزيع والمندوب في توضيح مسؤولية العهدة والتقارير التشغيلية.')
                    ->icon('heroicon-o-map-pin')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('route_id')
                            ->label('خط التوزيع')
                            ->relationship(
                                'route',
                                'name',
                                modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query
                                    ->where('status', 'active')
                                    ->when(
                                        $get('vehicle_id'),
                                        fn (Builder $query, $vehicleId): Builder => $query->where('vehicle_id', $vehicleId),
                                    ),
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (mixed $state, Set $set): void {
                                $context = OperationalFormContext::forRoute($state);

                                if ($context['vehicle_id'] !== null) {
                                    $set('vehicle_id', $context['vehicle_id']);
                                    $set('warehouse_id', $context['warehouse_id']);
                                }

                                $set('sales_representative_id', $context['sales_representative_id']);
                            })
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

                Section::make('مطابقة الصندوق')
                    ->description('أدخل النقد الفعلي الموجود في العهدة. يحسب النظام النقد المتوقع وفرق الصندوق من العمليات المؤكدة فقط.')
                    ->icon('heroicon-o-banknotes')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('actual_cash_amount')
                            ->label('النقد الفعلي المستلم')
                            ->numeric()
                            ->default(0)
                            ->prefix('SYP')
                            ->required()
                            ->helperText('يشمل النقد فقط؛ الشيكات والتحويلات ووسائل الدفع غير النقدية لا تدخل في فرق الصندوق.'),

                        Textarea::make('notes')
                            ->label('ملاحظات الإغلاق')
                            ->rows(3),
                    ]),

                Section::make('الجرد الفعلي للمواد')
                    ->description('بعد حفظ المسودة، حدّث الملخص الدفتري من صفحة التفاصيل ثم أدخل الجرد الفعلي لكل مادة. يظهر الفرق مباشرة مقابل الرصيد المتوقع.')
                    ->icon('heroicon-o-scale')
                    ->columnSpanFull()
                    ->hidden(fn (?DailyClosing $record): bool => ! $record?->exists)
                    ->schema([
                        Repeater::make('items')
                            ->label('مواد المطابقة')
                            ->relationship('items')
                            ->columns(6)
                            ->columnSpanFull()
                            ->defaultItems(0)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->collapsed(false)
                            ->schema([
                                Select::make('product_id')
                                    ->label('المنتج')
                                    ->relationship('product', 'name_ar')
                                    ->disabled()
                                    ->dehydrated()
                                    ->native(false)
                                    ->columnSpan(2),

                                TextInput::make('opening_quantity')
                                    ->label('رصيد البداية')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(),

                                TextInput::make('movement_in_quantity')
                                    ->label('الوارد الدفتري')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(),

                                TextInput::make('movement_out_quantity')
                                    ->label('الصادر الدفتري')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(),

                                TextInput::make('expected_quantity')
                                    ->label('الرصيد المتوقع')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(),

                                TextInput::make('actual_quantity')
                                    ->label('الجرد الفعلي')
                                    ->numeric()
                                    ->step('0.001')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                                        if ($state === null || $state === '') {
                                            $set('difference_quantity', null);

                                            return;
                                        }

                                        $set('difference_quantity', round(
                                            (float) $state - (float) ($get('expected_quantity') ?? 0),
                                            3,
                                        ));
                                    }),

                                TextInput::make('difference_quantity')
                                    ->label('فرق الجرد')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(),

                                TextInput::make('loaded_quantity')
                                    ->label('المحمّل تحليليًا')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(),

                                TextInput::make('sold_quantity')
                                    ->label('المباع تحليليًا')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(),

                                TextInput::make('returned_quantity')
                                    ->label('المرتجع تحليليًا')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(),

                                Textarea::make('notes')
                                    ->label('ملاحظات المادة')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
