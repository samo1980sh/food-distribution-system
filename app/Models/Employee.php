<?php

namespace App\Models;

use App\Enums\EmployeeType;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Employee extends Model
{
    protected $attributes = [
        'type' => EmployeeType::SALES_REPRESENTATIVE->value,
    ];

    protected $fillable = [
        'user_id',
        'employee_code',
        'name',
        'phone',
        'job_title',
        'type',
        'status',
        'notes',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $employee): void {
            $employeeType = EmployeeType::tryFrom((string) $employee->type);

            if ($employeeType === null) {
                throw ValidationException::withMessages([
                    'type' => 'نوع الموظف المحدد غير مدعوم.',
                ]);
            }

            if (! $employee->isDirty(['user_id', 'type'])) {
                return;
            }

            if ($employee->user_id === null) {
                return;
            }

            $expectedRole = $employeeType->userRole();

            $user = User::query()->find($employee->user_id);

            if ($user?->hasRole($expectedRole->value) === true) {
                return;
            }

            throw ValidationException::withMessages([
                'user_id' => 'يجب أن يطابق دور حساب المستخدم نوع الموظف المحدد.',
            ]);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForOperationalRole(
        Builder $query,
        UserRole|string $role,
    ): Builder {
        $roleValue = $role instanceof UserRole ? $role->value : $role;

        return $query->where(function (Builder $query) use ($roleValue): void {
            $query
                ->where('type', $roleValue)
                ->orWhereHas('user', function (Builder $userQuery) use ($roleValue): void {
                    $userQuery
                        ->where('status', User::STATUS_ACTIVE)
                        ->role($roleValue);
                });
        });
    }

    public function canFulfillOperationalRole(UserRole|string $role): bool
    {
        $roleValue = $role instanceof UserRole ? $role->value : $role;

        return $this->type === $roleValue
            || (
                $this->user?->isActive() === true
                && $this->user->hasRole($roleValue)
            );
    }

    public function salesRoutes(): HasMany
    {
        return $this->hasMany(DistributionRoute::class, 'sales_representative_id');
    }

    public function salesRepresentativeVehicleExpenses(): HasMany
    {
        return $this->hasMany(VehicleExpense::class, 'sales_representative_id');
    }
}
