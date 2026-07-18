<?php

namespace App\Filament\Resources\Areas\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AreaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('بيانات المنطقة')
                    ->description('الرمز والاسم والمدينة المستخدمة في العملاء وخطوط التوزيع ونطاقات المشرفين.')
                    ->icon('heroicon-o-map-pin')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('code')
                            ->label('رمز المنطقة')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        TextInput::make('name_ar')
                            ->label('اسم المنطقة')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('city')
                            ->label('المدينة / المحافظة')
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
                            ->helperText('تعطيل المنطقة يمنع استخدامها في السجلات الجديدة مع المحافظة على التاريخ.'),
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
