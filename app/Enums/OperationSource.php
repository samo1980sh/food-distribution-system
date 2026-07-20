<?php

namespace App\Enums;

enum OperationSource: string
{
    case MOBILE_SALES = 'mobile_sales';
    case MOBILE_DRIVER = 'mobile_driver';
    case OFFICE = 'office';
    case ADMIN_EXCEPTION = 'admin_exception';
    case SYSTEM = 'system';
    case LEGACY = 'legacy';

    public function label(): string
    {
        return match ($this) {
            self::MOBILE_SALES => 'تطبيق مندوب المبيعات',
            self::MOBILE_DRIVER => 'تطبيق السائق',
            self::OFFICE => 'إدخال مكتبي',
            self::ADMIN_EXCEPTION => 'إدخال إداري استثنائي',
            self::SYSTEM => 'النظام',
            self::LEGACY => 'بيانات سابقة',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::MOBILE_SALES, self::MOBILE_DRIVER => 'info',
            self::OFFICE => 'success',
            self::ADMIN_EXCEPTION => 'warning',
            self::SYSTEM => 'primary',
            self::LEGACY => 'gray',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::MOBILE_SALES, self::MOBILE_DRIVER => 'heroicon-o-device-phone-mobile',
            self::OFFICE => 'heroicon-o-building-office-2',
            self::ADMIN_EXCEPTION => 'heroicon-o-exclamation-triangle',
            self::SYSTEM => 'heroicon-o-cog-6-tooth',
            self::LEGACY => 'heroicon-o-archive-box',
        };
    }

    public static function fromState(mixed $state): self
    {
        if ($state instanceof self) {
            return $state;
        }

        return self::tryFrom((string) $state) ?? self::LEGACY;
    }

    public static function labelFor(mixed $state): string
    {
        return self::fromState($state)->label();
    }

    public static function colorFor(mixed $state): string
    {
        return self::fromState($state)->color();
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $source) {
            $options[$source->value] = $source->label();
        }

        return $options;
    }

    public static function mobileForEntity(string $entity): self
    {
        return $entity === 'vehicle_expenses'
            ? self::MOBILE_DRIVER
            : self::MOBILE_SALES;
    }
}
