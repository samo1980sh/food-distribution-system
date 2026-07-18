<?php

namespace App\Filament\Resources\Units\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UnitForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('بيانات الوحدة')
                    ->description('الاسم والاختصار المستخدمان في المنتجات والكميات والتقارير.')
                    ->icon('heroicon-o-scale')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('code')
                            ->label('رمز الوحدة')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('name_ar')
                            ->label('اسم الوحدة')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('symbol')
                            ->label('الاختصار')
                            ->maxLength(255),
                        Select::make('status')
                            ->label('الحالة')
                            ->options([
                                'active' => 'فعال',
                                'inactive' => 'غير فعال',
                            ])
                            ->default('active')
                            ->required()
                            ->native(false)
                            ->helperText('تعطيل الوحدة يمنع ربطها بمنتجات جديدة دون التأثير على المنتجات الحالية.'),
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
