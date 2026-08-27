<?php

namespace App\Filament\Resources\Suppliers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SupplierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('هوية المورد')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('code')
                            ->label('رمز المورد')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(100),
                        TextInput::make('name')
                            ->label('اسم المورد')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('contact_person')
                            ->label('مسؤول التواصل')
                            ->maxLength(255),
                        Select::make('status')
                            ->label('الحالة')
                            ->options([
                                'active' => 'فعال',
                                'inactive' => 'غير فعال',
                            ])
                            ->default('active')
                            ->required()
                            ->native(false),
                    ]),

                Section::make('التواصل والبيانات')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('phone')->label('الهاتف')->maxLength(100),
                        TextInput::make('mobile')->label('الجوال')->maxLength(100),
                        TextInput::make('email')->label('البريد الإلكتروني')->email()->maxLength(255),
                        TextInput::make('tax_number')->label('الرقم الضريبي')->maxLength(255),
                        Textarea::make('address')->label('العنوان')->rows(3)->columnSpanFull(),
                        Textarea::make('notes')->label('ملاحظات')->rows(3)->columnSpanFull(),
                    ]),
            ]);
    }
}
