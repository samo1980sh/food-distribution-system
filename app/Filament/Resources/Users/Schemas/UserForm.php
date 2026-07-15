<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

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

                Select::make('roles')
                    ->label('الدور')
                    ->relationship(
                        name: 'roles',
                        titleAttribute: 'name',
                        modifyQueryUsing: function (Builder $query): Builder {
                            if (auth()->user()?->isSuperAdmin() === true) {
                                return $query;
                            }

                            return $query->where(
                                'name',
                                '!=',
                                UserRole::SUPER_ADMIN->value,
                            );
                        },
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn (Role $record): string => UserRole::tryFrom($record->name)?->label()
                            ?? $record->name,
                    )
                    ->default(
                        fn (): array => Role::query()
                            ->where('name', UserRole::MANAGER->value)
                            ->pluck('id')
                            ->all(),
                    )
                    ->multiple()
                    ->maxItems(1)
                    ->required()
                    ->preload()
                    ->searchable()
                    ->native(false)
                    ->disabled(
                        fn (?User $record): bool => $record?->is(auth()->user()) === true
                            || $record?->isLastActiveSuperAdmin() === true,
                    )
                    ->helperText(
                        fn (?User $record): string => $record?->isLastActiveSuperAdmin() === true
                            ? 'لا يمكن تغيير دور آخر مدير نظام فعّال.'
                            : 'لا يمكن للمستخدم تغيير دوره بنفسه.',
                    ),

                Select::make('status')
                    ->label('الحالة')
                    ->options([
                        User::STATUS_ACTIVE => 'فعّال',
                        User::STATUS_INACTIVE => 'غير فعّال',
                    ])
                    ->default(User::STATUS_ACTIVE)
                    ->required()
                    ->native(false)
                    ->disabled(
                        fn (?User $record): bool => $record?->is(auth()->user()) === true
                            || $record?->isLastActiveSuperAdmin() === true,
                    )
                    ->helperText(
                        fn (?User $record): string => $record?->isLastActiveSuperAdmin() === true
                            ? 'لا يمكن تعطيل آخر مدير نظام فعّال.'
                            : 'لا يمكن للمستخدم تعطيل حسابه بنفسه.',
                    ),

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
