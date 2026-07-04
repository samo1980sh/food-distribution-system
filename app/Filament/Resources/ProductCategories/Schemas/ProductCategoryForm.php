<?php

namespace App\Filament\Resources\ProductCategories\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ProductCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('code')->label('رمز التصنيف')->required()->unique(ignoreRecord: true)->maxLength(255),
                TextInput::make('name_ar')->label('اسم التصنيف بالعربية')->required()->maxLength(255),
                TextInput::make('name_en')->label('اسم التصنيف بالإنكليزية')->maxLength(255),
                Select::make('parent_id')->label('التصنيف الأب')->relationship('parent', 'name_ar')->searchable()->preload()->native(false),
                TextInput::make('sort_order')->label('الترتيب')->numeric()->integer()->default(0),

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