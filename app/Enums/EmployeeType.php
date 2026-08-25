<?php

namespace App\Enums;

enum EmployeeType: string
{
    case SALES_REPRESENTATIVE = 'sales_representative';
    case WAREHOUSE_KEEPER = 'warehouse_keeper';
    case ACCOUNTANT = 'accountant';
    case SUPERVISOR = 'supervisor';

    public function userRole(): UserRole
    {
        return match ($this) {
            self::SALES_REPRESENTATIVE => UserRole::SALES_REPRESENTATIVE,
            self::WAREHOUSE_KEEPER => UserRole::WAREHOUSE_KEEPER,
            self::ACCOUNTANT => UserRole::ACCOUNTANT,
            self::SUPERVISOR => UserRole::SUPERVISOR,
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
