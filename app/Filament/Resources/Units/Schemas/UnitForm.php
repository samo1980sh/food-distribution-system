<?php

namespace App\Filament\Resources\Units\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UnitForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('code')->label('رمز الوحدة')->required()->unique(ignoreRecord: true)->maxLength(255),
                TextInput::make('name_ar')->label('اسم الوحدة بالعربية')->required()->maxLength(255),
                TextInput::make('name_en')->label('اسم الوحدة بالإنكليزية')->maxLength(255),
                TextInput::make('symbol')->label('الاختصار')->maxLength(255),

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