<?php

namespace App\Filament\Resources\PurchaseOrders\Schemas;

use App\Models\Product;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class PurchaseOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('بيانات أمر الشراء')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('supplier_id')
                            ->label('المورد')
                            ->relationship(
                                'supplier',
                                'name',
                                modifyQueryUsing: fn (Builder $query): Builder => $query->where('status', 'active'),
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),
                        Select::make('warehouse_id')
                            ->label('مستودع الاستلام')
                            ->relationship(
                                'warehouse',
                                'name',
                                modifyQueryUsing: fn (Builder $query): Builder => $query
                                    ->where('status', 'active')
                                    ->whereIn('type', ['main', 'branch']),
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),
                        DatePicker::make('order_date')
                            ->label('تاريخ الأمر')
                            ->default(now())
                            ->required()
                            ->native(false),
                        DatePicker::make('expected_date')
                            ->label('تاريخ التوريد المتوقع')
                            ->native(false),
                        Textarea::make('notes')
                            ->label('ملاحظات')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('الأصناف المطلوبة')
                    ->description('الاستلام لا يحدث عند حفظ أو اعتماد الأمر؛ المخزون يتحرك فقط عند تسجيل استلام فعلي.')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('items')
                            ->label('بنود أمر الشراء')
                            ->relationship('items')
                            ->minItems(1)
                            ->columns(4)
                            ->schema([
                                Select::make('product_id')
                                    ->label('المنتج')
                                    ->relationship(
                                        'product',
                                        'name_ar',
                                        modifyQueryUsing: fn (Builder $query): Builder => $query->where('status', 'active'),
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->native(false)
                                    ->columnSpan(2),
                                TextInput::make('ordered_quantity')
                                    ->label('الكمية بوحدة التشغيل')
                                    ->numeric()
                                    ->step('0.001')
                                    ->minValue(0.001)
                                    ->suffix(fn (Get $get): string => self::productUnitLabel($get('product_id')))
                                    ->required(),
                                TextInput::make('unit_cost')
                                    ->label('تكلفة وحدة التشغيل')
                                    ->numeric()
                                    ->step('0.000001')
                                    ->minValue(0)
                                    ->suffix(fn (Get $get): string => 'ل.س / '.self::productUnitLabel($get('product_id')))
                                    ->required(),
                                Textarea::make('notes')
                                    ->label('ملاحظة الصنف')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    private static function productUnitLabel(mixed $productId): string
    {
        if (blank($productId)) {
            return 'وحدة التشغيل';
        }

        $product = Product::query()
            ->with('unit')
            ->find($productId);

        $label = trim((string) ($product?->unit?->symbol ?: $product?->unit?->name_ar));

        return $label !== '' ? $label : 'وحدة غير محددة';
    }

}
