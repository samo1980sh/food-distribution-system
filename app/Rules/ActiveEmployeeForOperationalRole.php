<?php

namespace App\Rules;

use App\Enums\UserRole;
use App\Models\Employee;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ActiveEmployeeForOperationalRole implements ValidationRule
{
    public function __construct(
        private readonly UserRole|string $role,
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $isEligible = Employee::withoutGlobalScopes()
            ->whereKey((int) $value)
            ->where('status', 'active')
            ->forOperationalRole($this->role)
            ->exists();

        if (! $isEligible) {
            $fail(__('validation.exists', ['attribute' => $attribute]));
        }
    }
}
