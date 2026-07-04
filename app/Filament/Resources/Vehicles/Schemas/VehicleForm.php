<?php

namespace App\Filament\Resources\Vehicles\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class VehicleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('code')->label('رمز السيارة')->required()->unique(ignoreRecord: true)->maxLength(255),
                TextInput::make('plate_number')->label('رقم اللوحة')->required()->unique(ignoreRecord: true)->maxLength(255),
                TextInput::make('name')->label('اسم / وصف السيارة')->maxLength(255),
                TextInput::make('vehicle_type')->label('نوع السيارة')->maxLength(255),
                TextInput::make('capacity')->label('سعة التحميل')->numeric()->step('0.001'),
                TextInput::make('current_odometer')->label('عداد الكيلومترات')->numeric()->integer(),
                DatePicker::make('insurance_expiry_date')->label('انتهاء التأمين'),
                DatePicker::make('license_expiry_date')->label('انتهاء الترخيص'),

                Select::make('status')
                    ->label('الحالة')
                    ->options([
                        'active' => 'فعالة',
                        'maintenance' => 'صيانة',
                        'inactive' => 'خارج الخدمة',
                    ])
                    ->default('active')
                    ->required()
                    ->native(false),

                Textarea::make('notes')->label('ملاحظات')->columnSpanFull(),
            ]);
    }
}