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
                DatePicker::make('closing_date')
                    ->label('تاريخ الإغلاق')
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
                        $context = OperationalFormContext::forRoute($state);

                        if ($context['vehicle_id'] !== null) {
                            $set('vehicle_id', $context['vehicle_id']);
                            $set('warehouse_id', $context['warehouse_id']);
                        }

                        $set('sales_representative_id', $context['sales_representative_id']);
                    })
                    ->native(false),

                Select::make('warehouse_id')
                    ->label('مستودع السيارة / المستودع')
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

                TextInput::make('actual_cash_amount')
                    ->label('النقد الفعلي المستلم')
                    ->numeric()
                    ->default(0)
                    ->helperText('أدخل الكاش فقط. الشيكات والتحويلات لا تدخل في فرق الصندوق.'),

                Textarea::make('notes')
                    ->label('ملاحظات')
                    ->columnSpanFull(),

                Repeater::make('items')
                    ->label('ملخص المواد')
                    ->relationship('items')
                    ->columns(2)
                    ->columnSpanFull()
                    ->defaultItems(0)
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->collapsed()
                    ->hidden(fn (?DailyClosing $record): bool => ! $record?->exists)
                    ->schema([
                        Select::make('product_id')
                            ->label('المنتج')
                            ->relationship('product', 'name_ar')
                            ->disabled()
                            ->dehydrated()
                            ->native(false)
                            ->columnSpanFull(),

                        TextInput::make('loaded_quantity')->label('المحمّل')->numeric()->disabled()->dehydrated(),
                        TextInput::make('sold_quantity')->label('المباع')->numeric()->disabled()->dehydrated(),
                        TextInput::make('returned_quantity')->label('المرتجع')->numeric()->disabled()->dehydrated(),
                        TextInput::make('expected_quantity')->label('المتوقع')->numeric()->disabled()->dehydrated(),
                        TextInput::make('actual_quantity')->label('الجرد الفعلي')->numeric()->step('0.001'),
                        TextInput::make('difference_quantity')->label('الفرق')->numeric()->disabled()->dehydrated(),
                        Textarea::make('notes')->label('ملاحظات المادة')->columnSpanFull(),
                    ]),
            ]);
    }
}
