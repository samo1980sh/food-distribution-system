<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label('الاسم')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->label('البريد الإلكتروني')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                Select::make('role')
                    ->label('الدور')
                    ->options([
                        'super_admin' => 'مدير النظام',
                        'manager' => 'مدير',
                        'supervisor' => 'مشرف',
                        'warehouse_keeper' => 'أمين مستودع',
                        'accountant' => 'محاسب',
                    ])
                    ->default('manager')
                    ->required()
                    ->native(false),

                Select::make('status')
                    ->label('الحالة')
                    ->options([
                        'active' => 'فعّال',
                        'inactive' => 'غير فعّال',
                    ])
                    ->default('active')
                    ->required()
                    ->native(false),

                TextInput::make('password')
                    ->label('كلمة المرور')
                    ->password()
                    ->revealable()
                    ->maxLength(255)
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText('اتركها فارغة عند التعديل إذا لم ترغب بتغيير كلمة المرور.')
                    ->columnSpanFull(),
            ]);
    }
}