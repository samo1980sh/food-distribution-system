<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class AllowedAdminEmployeeType implements ValidationRule
{
    public function __construct(
        private readonly ?string $existingType = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === 'driver' && $this->existingType !== 'driver') {
            $fail('نوع السائق مخصص للسجلات التاريخية ولا يمكن تعيينه لموظف جديد أو لموظف حالي.');
        }
    }
}
