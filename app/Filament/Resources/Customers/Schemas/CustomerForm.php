<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Models\DistributionRoute;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('هوية العميل')
                    ->description('البيانات الأساسية التي تظهر في الفواتير والتحصيلات والتقارير.')
                    ->icon('heroicon-o-building-storefront')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('code')
                            ->label('رمز العميل')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('name')
                            ->label('اسم العميل / المحل')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('owner_name')
                            ->label('اسم صاحب المحل')
                            ->maxLength(255),
                        Select::make('customer_type')
                            ->label('نوع العميل')
                            ->options([
                                'grocery' => 'بقالية',
                                'supermarket' => 'سوبر ماركت',
                                'restaurant' => 'مطعم',
                                'wholesaler' => 'موزع / جملة',
                                'mini_market' => 'ميني ماركت',
                                'other' => 'أخرى',
                            ])
                            ->default('grocery')
                            ->required()
                            ->native(false),
                        Select::make('status')
                            ->label('الحالة')
                            ->options([
                                'active' => 'فعال',
                                'inactive' => 'غير فعال',
                            ])
                            ->default('active')
                            ->required()
                            ->native(false)
                            ->helperText('التعطيل يمنع استخدام العميل في العمليات الجديدة مع إبقاء تاريخه المالي.'),
                    ]),

                Section::make('الاتصال والموقع والتوزيع')
                    ->description('يجب أن يتبع خط التوزيع المنطقة المحددة؛ يتحقق النظام من هذا التطابق عند الحفظ.')
                    ->icon('heroicon-o-map')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('phone')->label('الهاتف')->tel()->maxLength(255),
                        TextInput::make('mobile')->label('الموبايل')->tel()->maxLength(255),
                        Select::make('area_id')
                            ->label('المنطقة')
                            ->relationship(
                                'area',
                                'name_ar',
                                modifyQueryUsing: fn (Builder $query): Builder => $query->where('status', 'active'),
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('route_id', null);
                            })
                            ->native(false),
                        Select::make('route_id')
                            ->label('خط التوزيع')
                            ->relationship(
                                'route',
                                'name',
                                modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query
                                    ->where('status', 'active')
                                    ->when(
                                        $get('area_id'),
                                        fn (Builder $query, $areaId): Builder => $query->where('area_id', $areaId),
                                    ),
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (mixed $state, Set $set): void {
                                $route = filled($state)
                                    ? DistributionRoute::query()->find((int) $state)
                                    : null;

                                if ($route !== null) {
                                    $set('area_id', $route->area_id);
                                }
                            })
                            ->native(false)
                            ->helperText('تعرض القائمة الخطوط الفعالة التابعة للمنطقة المحددة فقط.'),
                        TextInput::make('address')
                            ->label('العنوان التفصيلي')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('latitude')
                            ->label('خط العرض')
                            ->numeric()
                            ->step('0.0000001'),
                        TextInput::make('longitude')
                            ->label('خط الطول')
                            ->numeric()
                            ->step('0.0000001'),
                    ]),

                Section::make('السياسة الائتمانية')
                    ->description('تستخدم هذه القيم في تاريخ استحقاق الفاتورة والتحقق من حد الائتمان عند الاعتماد.')
                    ->icon('heroicon-o-banknotes')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('credit_limit')
                            ->label('حد الائتمان')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->helperText('القيمة صفر تعني عدم فرض حد ائتماني آلي.'),
                        TextInput::make('credit_days')
                            ->label('مدة الائتمان بالأيام')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(365)
                            ->default(30)
                            ->required(),
                        Select::make('payment_type')
                            ->label('طريقة الدفع المعتادة')
                            ->options([
                                'cash' => 'نقدي',
                                'credit' => 'آجل',
                                'weekly' => 'أسبوعي',
                                'monthly' => 'شهري',
                            ])
                            ->default('cash')
                            ->required()
                            ->native(false),
                    ]),

                Section::make('ملاحظات داخلية')
                    ->icon('heroicon-o-document-text')
                    ->columnSpanFull()
                    ->collapsible()
                    ->schema([
                        Textarea::make('notes')
                            ->label('ملاحظات')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
