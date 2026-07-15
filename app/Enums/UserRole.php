<?php

namespace App\Enums;

enum UserRole: string
{
    case SUPER_ADMIN = 'super_admin';
    case MANAGER = 'manager';
    case SUPERVISOR = 'supervisor';
    case WAREHOUSE_KEEPER = 'warehouse_keeper';
    case ACCOUNTANT = 'accountant';
    case SALES_REPRESENTATIVE = 'sales_representative';
    case DRIVER = 'driver';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'مدير النظام',
            self::MANAGER => 'مدير',
            self::SUPERVISOR => 'مشرف توزيع',
            self::WAREHOUSE_KEEPER => 'أمين مستودع',
            self::ACCOUNTANT => 'محاسب',
            self::SALES_REPRESENTATIVE => 'مندوب مبيعات',
            self::DRIVER => 'سائق',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'danger',
            self::MANAGER => 'primary',
            self::SUPERVISOR => 'info',
            self::WAREHOUSE_KEEPER => 'warning',
            self::ACCOUNTANT => 'success',
            self::SALES_REPRESENTATIVE => 'gray',
            self::DRIVER => 'gray',
        };
    }

    /** @return array<string, string> */
    public static function options(bool $includeSuperAdmin = true): array
    {
        $roles = self::cases();

        if (! $includeSuperAdmin) {
            $roles = array_filter(
                $roles,
                fn (self $role): bool => $role !== self::SUPER_ADMIN,
            );
        }

        return array_column(
            array_map(
                fn (self $role): array => [$role->value, $role->label()],
                $roles,
            ),
            1,
            0,
        );
    }

    /** @return list<string> */
    public static function panelValues(): array
    {
        return array_map(
            fn (self $role): string => $role->value,
            [
                self::SUPER_ADMIN,
                self::MANAGER,
                self::SUPERVISOR,
                self::WAREHOUSE_KEEPER,
                self::ACCOUNTANT,
            ],
        );
    }
}
