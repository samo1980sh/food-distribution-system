<?php

namespace App\Rules;

use App\Enums\UserRole;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Spatie\Permission\Models\Role;

final class AllowedUserRoleCombination implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $roleIds = collect((array) $value)
            ->filter(fn (mixed $roleId): bool => is_numeric($roleId))
            ->map(fn (mixed $roleId): int => (int) $roleId)
            ->filter(fn (int $roleId): bool => $roleId > 0)
            ->unique()
            ->values();

        if ($roleIds->isEmpty() || $roleIds->count() > 2) {
            $fail('يجب اختيار دور واحد، أو دوري السائق ومندوب المبيعات معاً فقط.');

            return;
        }

        $roleNames = Role::query()
            ->whereKey($roleIds->all())
            ->pluck('name')
            ->sort()
            ->values();

        if ($roleNames->count() !== $roleIds->count()) {
            $fail('يتضمن اختيار الأدوار قيمة غير صالحة.');

            return;
        }

        if ($roleNames->count() === 1) {
            return;
        }

        $allowedFieldCombination = collect([
            UserRole::DRIVER->value,
            UserRole::SALES_REPRESENTATIVE->value,
        ])->sort()->values();

        if ($roleNames->all() !== $allowedFieldCombination->all()) {
            $fail('لا يمكن جمع أكثر من دور إلا للسائق ومندوب المبيعات معاً.');
        }
    }
}
