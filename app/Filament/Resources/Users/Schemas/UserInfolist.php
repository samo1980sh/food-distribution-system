<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use App\Models\Area;
use App\Models\DistributionRoute;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\Authorization\AccessScopeService;
use App\Support\Authorization\EffectiveAccessScope;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('ملخص الحساب')
                    ->icon('heroicon-o-user-circle')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('name')->label('الاسم')->weight('bold'),
                        TextEntry::make('email')->label('البريد الإلكتروني')->copyable(),
                        TextEntry::make('status')
                            ->label('الحالة')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => $state === User::STATUS_ACTIVE ? 'فعّال' : 'غير فعّال')
                            ->color(fn (?string $state): string => $state === User::STATUS_ACTIVE ? 'success' : 'gray'),
                        TextEntry::make('roles_summary')
                            ->label('الأدوار')
                            ->state(fn (User $record): string => self::roleSummary($record))
                            ->badge()
                            ->color('primary'),
                    ]),

                Section::make('الموظف المرتبط')
                    ->description('الربط بالموظف هو مصدر النطاق المشتق لحسابات السائق ومندوب المبيعات.')
                    ->icon('heroicon-o-identification')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('employee.employee_code')->label('رمز الموظف')->placeholder('-'),
                        TextEntry::make('employee.name')->label('اسم الموظف')->placeholder('لا يوجد موظف مرتبط'),
                        TextEntry::make('employee.type')
                            ->label('نوع الموظف')
                            ->formatStateUsing(fn (?string $state): string => self::employeeTypeLabel($state))
                            ->placeholder('-'),
                        TextEntry::make('employee.status')
                            ->label('حالة الموظف')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => $state === 'active' ? 'فعال' : 'غير فعال')
                            ->color(fn (?string $state): string => $state === 'active' ? 'success' : 'gray')
                            ->placeholder('-'),
                    ]),

                Section::make('التعيينات المباشرة')
                    ->description('هذه القيم مدخلة يدويًا، ويقوم النظام بدمجها مع النطاقات المشتقة عند تنفيذ الاستعلامات والسياسات.')
                    ->icon('heroicon-o-map-pin')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('direct_areas')
                            ->label('المناطق المباشرة')
                            ->state(fn (User $record): string => self::relationNames($record, 'accessAreas', 'name_ar')),
                        TextEntry::make('direct_routes')
                            ->label('الخطوط المباشرة')
                            ->state(fn (User $record): string => self::relationNames($record, 'accessRoutes', 'name')),
                        TextEntry::make('direct_vehicles')
                            ->label('السيارات المباشرة')
                            ->state(fn (User $record): string => self::relationNames($record, 'accessVehicles', 'plate_number')),
                        TextEntry::make('direct_warehouses')
                            ->label('المستودعات المباشرة')
                            ->state(fn (User $record): string => self::relationNames($record, 'accessWarehouses', 'name')),
                    ]),

                Section::make('نطاق الوصول الفعلي')
                    ->description('النطاق النهائي بعد دمج الدور، التعيينات المباشرة، الموظف، الخطوط، السيارات والمستودعات المرتبطة.')
                    ->icon('heroicon-o-shield-check')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('effective_scope_mode')
                            ->label('نمط الوصول')
                            ->state(fn (User $record): string => self::scope($record)->unrestricted ? 'وصول شامل' : 'وصول مقيّد')
                            ->badge()
                            ->color(fn (User $record): string => self::scope($record)->unrestricted ? 'success' : 'warning'),
                        TextEntry::make('effective_scope_health')
                            ->label('جاهزية النطاق')
                            ->state(fn (User $record): string => self::scopeHealth($record))
                            ->badge()
                            ->color(fn (User $record): string => self::scope($record)->hasAssignments() ? 'success' : 'danger'),
                        TextEntry::make('effective_areas')
                            ->label('المناطق الفعلية')
                            ->state(fn (User $record): string => self::effectiveNames($record, Area::class, self::scope($record)->areaIds, 'name_ar')),
                        TextEntry::make('effective_routes')
                            ->label('الخطوط الفعلية')
                            ->state(fn (User $record): string => self::effectiveNames($record, DistributionRoute::class, self::scope($record)->routeIds, 'name')),
                        TextEntry::make('effective_vehicles')
                            ->label('السيارات الفعلية')
                            ->state(fn (User $record): string => self::effectiveNames($record, Vehicle::class, self::scope($record)->vehicleIds, 'plate_number')),
                        TextEntry::make('effective_warehouses')
                            ->label('المستودعات الفعلية')
                            ->state(fn (User $record): string => self::effectiveNames($record, Warehouse::class, self::scope($record)->warehouseIds, 'name')),
                    ]),

                Section::make('الصلاحيات والأمان')
                    ->icon('heroicon-o-key')
                    ->columns(4)
                    ->columnSpanFull()
                    ->collapsible()
                    ->schema([
                        TextEntry::make('permissions_count')
                            ->label('عدد الصلاحيات الفعلية')
                            ->state(fn (User $record): int => $record->getAllPermissions()->count()),
                        TextEntry::make('mobile_sessions_count')
                            ->label('جلسات الجوال الحالية')
                            ->state(fn (User $record): int => $record->tokens()->count()),
                        TextEntry::make('created_at')->label('تاريخ الإنشاء')->dateTime('Y-m-d H:i'),
                        TextEntry::make('updated_at')->label('آخر تحديث')->dateTime('Y-m-d H:i'),
                    ]),
            ]);
    }

    private static function roleSummary(User $record): string
    {
        $roles = $record->getRoleNames()
            ->map(fn (string $role): string => UserRole::tryFrom($role)?->label() ?? $role)
            ->values();

        return $roles->isEmpty() ? 'دون دور' : $roles->implode('، ');
    }

    private static function employeeTypeLabel(?string $type): string
    {
        return match ($type) {
            'driver' => 'سائق',
            'sales_representative' => 'مندوب مبيعات',
            'warehouse_keeper' => 'أمين مستودع',
            'accountant' => 'محاسب',
            'supervisor' => 'مشرف',
            default => $type ?? '-',
        };
    }

    private static function relationNames(User $record, string $relationship, string $column): string
    {
        $values = $record->{$relationship}()
            ->withoutGlobalScopes()
            ->orderBy($column)
            ->pluck($column)
            ->filter()
            ->values();

        return $values->isEmpty() ? 'لا توجد تعيينات مباشرة' : $values->implode('، ');
    }

    private static function scope(User $record): EffectiveAccessScope
    {
        return app(AccessScopeService::class)->for($record);
    }

    private static function scopeHealth(User $record): string
    {
        $scope = self::scope($record);

        if ($scope->unrestricted) {
            return 'لا يحتاج تعيينًا مباشرًا';
        }

        return $scope->hasAssignments() ? 'النطاق جاهز' : 'النطاق فارغ';
    }

    /** @param class-string<\Illuminate\Database\Eloquent\Model> $modelClass
     *  @param list<int> $ids
     */
    private static function effectiveNames(User $record, string $modelClass, array $ids, string $column): string
    {
        if (self::scope($record)->unrestricted) {
            return 'جميع السجلات المسموح بها';
        }

        if ($ids === []) {
            return 'لا توجد سجلات ضمن النطاق';
        }

        $values = $modelClass::withoutGlobalScopes()
            ->whereKey($ids)
            ->orderBy($column)
            ->pluck($column)
            ->filter()
            ->values();

        return $values->isEmpty() ? 'لا توجد سجلات ضمن النطاق' : $values->implode('، ');
    }


}
