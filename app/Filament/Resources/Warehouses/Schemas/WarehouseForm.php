<?php

namespace App\Filament\Resources\Warehouses\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class WarehouseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('code')->label('رمز المستودع')->required()->unique(ignoreRecord: true)->maxLength(255),
                TextInput::make('name')->label('اسم المستودع')->required()->maxLength(255),

                Select::make('type')
                    ->label('نوع المستودع')
                    ->options([
                        'main' => 'رئيسي',
                        'branch' => 'فرعي',
                        'vehicle' => 'سيارة / مستودع متنقل',
                    ])
                    ->default('main')
                    ->required()
                    ->native(false),

                Select::make('vehicle_id')->label('السيارة المرتبطة')->relationship('vehicle', 'plate_number')->searchable()->preload()->native(false),
                TextInput::make('address')->label('العنوان')->maxLength(255)->columnSpanFull(),

                Select::make('status')
                    ->label('الحالة')
                    ->options([
                        'active' => 'فعال',
                        'inactive' => 'غير فعال',
                    ])
                    ->default('active')
                    ->required()
                    ->native(false),

                Textarea::make('notes')->label('ملاحظات')->columnSpanFull(),
            ]);
    }
}