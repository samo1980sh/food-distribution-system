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

        if ($roleIds->count() !== 1) {
            $fail('يجب اختيار دور واحد فقط.');

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

        if ($roleNames->diff(array_column(UserRole::cases(), 'value'))->isNotEmpty()) {
            $fail('يتضمن اختيار الأدوار دورًا غير مدعوم.');
        }
    }
}
