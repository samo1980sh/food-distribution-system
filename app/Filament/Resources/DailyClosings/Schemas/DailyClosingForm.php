<?php

namespace App\Filament\Resources\DailyClosings\Schemas;

use App\Models\DailyClosing;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

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
                    ->relationship('vehicle', 'plate_number')
                    ->searchable()
                    ->preload()
                    ->native(false),

                Select::make('route_id')
                    ->label('خط التوزيع')
                    ->relationship('route', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),

                Select::make('warehouse_id')
                    ->label('مستودع السيارة / المستودع')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->native(false),

                Select::make('sales_representative_id')
                    ->label('مندوب المبيعات')
                    ->relationship('salesRepresentative', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),

                TextInput::make('actual_cash_amount')
                    ->label('النقد الفعلي المستلم')
                    ->numeric()
                    ->default(0)
                    ->helperText('أدخل الكاش فقط. الشيكات والتحويلات تظهر ضمن التحصيل غير النقدي ولا تدخل في فرق الصندوق.'),

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

                        TextInput::make('loaded_quantity')
                            ->label('المحمّل')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(),

                        TextInput::make('sold_quantity')
                            ->label('المباع')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(),

                        TextInput::make('returned_quantity')
                            ->label('المرتجع')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(),

                        TextInput::make('expected_quantity')
                            ->label('المتوقع')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(),

                        TextInput::make('actual_quantity')
                            ->label('الجرد الفعلي')
                            ->numeric()
                            ->step('0.001'),

                        TextInput::make('difference_quantity')
                            ->label('الفرق')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(),

                        Textarea::make('notes')
                            ->label('ملاحظات المادة')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
