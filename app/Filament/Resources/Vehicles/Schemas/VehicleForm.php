<?php

namespace App\Filament\Resources\Vehicles\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VehicleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('هوية السيارة')
                    ->description('الرمز واللوحة والوصف والنوع والحالة المستخدمة في الخطوط والمستودعات والعمليات.')
                    ->icon('heroicon-o-truck')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('code')
                            ->label('رمز السيارة')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('plate_number')
                            ->label('رقم اللوحة')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('name')
                            ->label('اسم / وصف السيارة')
                            ->maxLength(255),
                        TextInput::make('vehicle_type')
                            ->label('نوع السيارة')
                            ->maxLength(255),
                        Select::make('status')
                            ->label('الحالة التشغيلية')
                            ->options([
                                'active' => 'فعالة',
                                'maintenance' => 'صيانة',
                                'inactive' => 'خارج الخدمة',
                            ])
                            ->default('active')
                            ->required()
                            ->native(false)
                            ->helperText('السيارة الفعالة فقط تظهر في العمليات الجديدة.'),
                    ]),

                Section::make('السعة والعداد')
                    ->icon('heroicon-o-chart-bar')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('capacity')
                            ->label('سعة التحميل')
                            ->numeric()
                            ->step('0.001')
                            ->minValue(0),
                        TextInput::make('current_odometer')
                            ->label('عداد الكيلومترات')
                            ->numeric()
                            ->integer()
                            ->minValue(0),
                    ]),

                Section::make('الوثائق والتجديد')
                    ->description('تواريخ مرجعية لمتابعة التأمين والترخيص قبل انتهاء الصلاحية.')
                    ->icon('heroicon-o-document-check')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        DatePicker::make('insurance_expiry_date')
                            ->label('انتهاء التأمين')
                            ->native(false),
                        DatePicker::make('license_expiry_date')
                            ->label('انتهاء الترخيص')
                            ->native(false),
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
