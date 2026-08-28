<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('هوية المنتج')
                    ->description('الوحدة هنا هي وحدة التشغيل المعتمدة للمنتج؛ كل كميات المخزون والتحميل والبيع والمرتجعات والمشتريات تُسجل بهذه الوحدة ما لم تُبنَ دورة تحويل وحدات مستقلة لاحقًا.')
                    ->icon('heroicon-o-cube')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('sku')
                            ->label('SKU / رمز المنتج')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('barcode')
                            ->label('الباركود')
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('name_ar')
                            ->label('اسم المنتج')
                            ->required()
                            ->maxLength(255),
                        Select::make('category_id')
                            ->label('التصنيف')
                            ->relationship(
                                'category',
                                'name_ar',
                                modifyQueryUsing: fn (Builder $query): Builder => $query->where('status', 'active'),
                            )
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Select::make('unit_id')
                            ->label('وحدة التشغيل')
                            ->relationship(
                                'unit',
                                'name_ar',
                                modifyQueryUsing: fn (Builder $query): Builder => $query->where('status', 'active'),
                            )
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->helperText('مثال: عبوة، علبة، كرتونة، كغ. السعر والرصيد والتحميل والبيع كلها تُفهم بهذه الوحدة.'),
                        Select::make('status')
                            ->label('الحالة')
                            ->options([
                                'active' => 'فعال',
                                'inactive' => 'غير فعال',
                            ])
                            ->default('active')
                            ->required()
                            ->native(false)
                            ->helperText('التعطيل يمنع استخدام المنتج في العمليات الجديدة مع المحافظة على الحركات التاريخية.'),
                    ]),

                Section::make('الأسعار')
                    ->description('أسعار مرجعية؛ تكلفة المخزون الفعلية تُدار وفق المتوسط المرجح للحركات.')
                    ->icon('heroicon-o-banknotes')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('purchase_price')
                            ->label('سعر الشراء المرجعي / وحدة التشغيل')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        TextInput::make('sale_price')
                            ->label('سعر البيع / وحدة التشغيل')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        TextInput::make('wholesale_price')
                            ->label('سعر الجملة / وحدة التشغيل')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                    ]),

                Section::make('ضوابط المخزون والصلاحية')
                    ->description('حد التنبيه لا يغيّر الأرصدة، وتُجرى أي حركة مخزون من شاشة حركات المخزون أو العمليات المعتمدة.')
                    ->icon('heroicon-o-archive-box')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('min_stock')
                            ->label('حد التنبيه / وحدة التشغيل')
                            ->numeric()
                            ->step('0.001')
                            ->minValue(0)
                            ->default(0),
                        Toggle::make('has_expiry')
                            ->label('يتطلب تاريخ صلاحية')
                            ->default(true)
                            ->helperText('فعّله للمواد التي يجب تتبع التشغيلات وتواريخ صلاحيتها.'),
                    ]),

                Section::make('ملاحظات داخلية')
                    ->icon('heroicon-o-document-text')
                    ->columnSpanFull()
                    ->collapsible()
                    ->schema([
                        Textarea::make('notes')->label('ملاحظات')->rows(4)->columnSpanFull(),
                    ]),
            ]);
    }
}
