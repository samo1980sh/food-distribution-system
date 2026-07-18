<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use App\Models\User;
use App\Rules\AllowedUserRoleCombination;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
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
                Section::make('بيانات الحساب')
                    ->description('بيانات الهوية المستخدمة في لوحة الإدارة أو تطبيق الجوال حسب الدور المعيّن.')
                    ->icon('heroicon-o-identification')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
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
                    ]),

                Section::make('الأدوار وقناة العمل')
                    ->description('الأدوار ثابتة ضمن مصفوفة الصلاحيات. يسمح فقط بالجمع بين السائق ومندوب المبيعات للحساب الميداني نفسه.')
                    ->icon('heroicon-o-shield-check')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('roles')
                            ->label('الأدوار')
                            ->relationship(
                                name: 'roles',
                                titleAttribute: 'name',
                                modifyQueryUsing: function (Builder $query): Builder {
                                    if (auth()->user()?->isSuperAdmin() === true) {
                                        return $query;
                                    }

                                    return $query->where('name', '!=', UserRole::SUPER_ADMIN->value);
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
                            ->maxItems(2)
                            ->rules([new AllowedUserRoleCombination])
                            ->required()
                            ->live()
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
                                    : 'الأدوار الإدارية تعمل عبر Filament، أما السائق ومندوب المبيعات فيعملان عبر تطبيق الجوال.',
                            )
                            ->columnSpanFull(),
                    ]),

                Section::make('نطاقات الوصول المباشرة')
                    ->description('تُدمج هذه التعيينات مع النطاقات المشتقة من الموظف والخطوط والسيارات والمستودعات.')
                    ->icon('heroicon-o-map')
                    ->columns(2)
                    ->columnSpanFull()
                    ->visible(fn (Get $get): bool => self::usesDirectScopes($get))
                    ->schema([
                        Select::make('accessAreas')
                            ->label('المناطق المسموح بها')
                            ->relationship(
                                'accessAreas',
                                'name_ar',
                                modifyQueryUsing: fn (Builder $query): Builder => $query->where('status', 'active'),
                            )
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->visible(fn (Get $get): bool => self::hasRole($get, UserRole::SUPERVISOR))
                            ->helperText('تمنح المنطقة خطوطها وعملاءها والسيارات والمستودعات المشتقة منها.'),

                        Select::make('accessRoutes')
                            ->label('خطوط التوزيع الإضافية')
                            ->relationship(
                                'accessRoutes',
                                'name',
                                modifyQueryUsing: fn (Builder $query): Builder => $query->where('status', 'active'),
                            )
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->visible(fn (Get $get): bool => self::hasRole($get, UserRole::SUPERVISOR))
                            ->helperText('تُضاف إلى الخطوط المشتقة من المناطق المحددة.'),

                        Select::make('accessVehicles')
                            ->label('السيارات الإضافية')
                            ->relationship(
                                'accessVehicles',
                                'plate_number',
                                modifyQueryUsing: fn (Builder $query): Builder => $query->where('status', 'active'),
                            )
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->visible(fn (Get $get): bool => self::hasRole($get, UserRole::SUPERVISOR))
                            ->helperText('تُستخدم عند الحاجة لمنح المشرف سيارة خارج خطوطه الأساسية.'),

                        Select::make('accessWarehouses')
                            ->label('المستودعات المسموح بها')
                            ->relationship(
                                'accessWarehouses',
                                'name',
                                modifyQueryUsing: fn (Builder $query): Builder => $query->where('status', 'active'),
                            )
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->visible(fn (Get $get): bool => self::hasAnyRole($get, [
                                UserRole::SUPERVISOR,
                                UserRole::WAREHOUSE_KEEPER,
                            ]))
                            ->helperText('لأمين المستودع هي مصدر نطاقه الأساسي، وللمشرف تُضاف إلى نطاقه المشتق.')
                            ->columnSpanFull(),
                    ]),

                Section::make('الحالة والأمان')
                    ->description('تعطيل الحساب يمنع تسجيل الدخول ويلغي جلسات تطبيق الجوال. تغيير كلمة المرور يلغي الجلسات أيضًا.')
                    ->icon('heroicon-o-lock-closed')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('status')
                            ->label('حالة الحساب')
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
                            ->minLength(8)
                            ->maxLength(255)
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText('مطلوبة عند الإنشاء. اتركها فارغة عند التعديل للمحافظة على كلمة المرور الحالية.'),
                    ]),
            ]);
    }

    private static function usesDirectScopes(Get $get): bool
    {
        return self::hasAnyRole($get, [UserRole::SUPERVISOR, UserRole::WAREHOUSE_KEEPER]);
    }

    /** @param list<UserRole> $roles */
    private static function hasAnyRole(Get $get, array $roles): bool
    {
        $selectedRoles = self::selectedRoles($get);

        foreach ($roles as $role) {
            if (in_array($role, $selectedRoles, true)) {
                return true;
            }
        }

        return false;
    }

    private static function hasRole(Get $get, UserRole $role): bool
    {
        return in_array($role, self::selectedRoles($get), true);
    }

    /** @return list<UserRole> */
    private static function selectedRoles(Get $get): array
    {
        $roleIds = array_values(array_filter((array) $get('roles')));

        if ($roleIds === []) {
            return [];
        }

        return Role::query()
            ->whereKey($roleIds)
            ->pluck('name')
            ->map(fn (string $name): ?UserRole => UserRole::tryFrom($name))
            ->filter()
            ->values()
            ->all();
    }
}
