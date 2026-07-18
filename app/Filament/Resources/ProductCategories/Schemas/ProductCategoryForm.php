<?php

namespace App\Filament\Resources\ProductCategories\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ProductCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('بيانات التصنيف')
                    ->description('التصنيف والفرع الأب وترتيب العرض المستخدم في دليل المنتجات.')
                    ->icon('heroicon-o-squares-2x2')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('code')
                            ->label('رمز التصنيف')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('name_ar')
                            ->label('اسم التصنيف')
                            ->required()
                            ->maxLength(255),
                        Select::make('parent_id')
                            ->label('التصنيف الأب')
                            ->relationship(
                                'parent',
                                'name_ar',
                                modifyQueryUsing: fn (Builder $query): Builder => $query->where('status', 'active'),
                            )
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->helperText('اتركه فارغًا للتصنيف الرئيسي.'),
                        TextInput::make('sort_order')
                            ->label('ترتيب العرض')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->default(0),
                        Select::make('status')
                            ->label('الحالة')
                            ->options([
                                'active' => 'فعال',
                                'inactive' => 'غير فعال',
                            ])
                            ->default('active')
                            ->required()
                            ->native(false)
                            ->helperText('التعطيل يمنع استخدام التصنيف للمنتجات الجديدة دون حذف المنتجات الحالية.'),
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
