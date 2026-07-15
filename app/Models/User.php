<?php

namespace App\Models;

use App\Enums\PermissionName;
use App\Enums\UserRole;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;
    use HasRoles {
        hasPermissionTo as private hasPermissionToThroughRoles;
    }

    public const ROLE_SUPER_ADMIN = UserRole::SUPER_ADMIN->value;
    public const ROLE_MANAGER = UserRole::MANAGER->value;
    public const ROLE_SUPERVISOR = UserRole::SUPERVISOR->value;
    public const ROLE_WAREHOUSE_KEEPER = UserRole::WAREHOUSE_KEEPER->value;
    public const ROLE_ACCOUNTANT = UserRole::ACCOUNTANT->value;
    public const ROLE_SALES_REPRESENTATIVE = UserRole::SALES_REPRESENTATIVE->value;
    public const ROLE_DRIVER = UserRole::DRIVER->value;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    /**
     * Keep the in-memory model consistent with the database default.
     *
     * Without this attribute, a newly created User that relies on the database
     * default is persisted as active, while the current Eloquent instance still
     * sees a null status until it is refreshed. Authorization checks must not
     * depend on that refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
    ];

    private ?string $pendingLegacyRole = null;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $user): void {
            if (
                $user->isDirty('status')
                && $user->status === self::STATUS_INACTIVE
                && $user->isLastActiveSuperAdmin()
            ) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن تعطيل آخر مدير نظام فعّال.',
                ]);
            }
        });

        static::created(function (self $user): void {
            if ($user->pendingLegacyRole === null) {
                return;
            }

            $role = UserRole::tryFrom($user->pendingLegacyRole);

            if ($role !== null) {
                $user->syncRoles([$role->value]);
            }

            $user->pendingLegacyRole = null;
        });
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function accessAreas(): BelongsToMany
    {
        return $this->belongsToMany(
            Area::class,
            'user_area_scopes',
        )->withTimestamps();
    }

    public function accessRoutes(): BelongsToMany
    {
        return $this->belongsToMany(
            DistributionRoute::class,
            'user_route_scopes',
        )->withTimestamps();
    }

    public function accessVehicles(): BelongsToMany
    {
        return $this->belongsToMany(
            Vehicle::class,
            'user_vehicle_scopes',
        )->withTimestamps();
    }

    public function accessWarehouses(): BelongsToMany
    {
        return $this->belongsToMany(
            Warehouse::class,
            'user_warehouse_scopes',
        )->withTimestamps();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        if (! Schema::hasTable('roles')) {
            return in_array(
                $this->attributes['role'] ?? null,
                UserRole::panelValues(),
                true,
            );
        }

        return $this->hasAnyRole(UserRole::panelValues())
            && $this->can(PermissionName::ADMIN_ACCESS->value);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Inactive accounts must never satisfy a Spatie permission check.
     *
     * Spatie registers its permission resolver as a Gate before callback, so
     * enforcing account status at the model permission boundary guarantees the
     * same result for Filament, policies, controllers, and the future API.
     */
    public function hasPermissionTo($permission, $guardName = null): bool
    {
        return $this->isActive()
            && $this->hasPermissionToThroughRoles($permission, $guardName);
    }

    public function isSuperAdmin(): bool
    {
        if (! Schema::hasTable('roles')) {
            return ($this->attributes['role'] ?? null)
                === UserRole::SUPER_ADMIN->value;
        }

        return $this->hasRole(UserRole::SUPER_ADMIN->value);
    }

    public function primaryRoleName(): ?string
    {
        if (! Schema::hasTable('roles')) {
            return $this->attributes['role'] ?? null;
        }

        return $this->getRoleNames()->first();
    }

    public function isLastActiveSuperAdmin(): bool
    {
        if (! $this->exists || ! $this->isSuperAdmin()) {
            return false;
        }

        $originalStatus = $this->getOriginal('status') ?? $this->status;

        if ($originalStatus !== self::STATUS_ACTIVE) {
            return false;
        }

        return self::query()
            ->where($this->getKeyName(), '!=', $this->getKey())
            ->where('status', self::STATUS_ACTIVE)
            ->role(UserRole::SUPER_ADMIN->value)
            ->doesntExist();
    }

    /**
     * Compatibility accessor for legacy views and tests during the RBAC migration.
     */
    public function getRoleAttribute(): ?string
    {
        return $this->primaryRoleName();
    }

    /**
     * Compatibility mutator for legacy factories during the RBAC migration.
     */
    public function setRoleAttribute(?string $role): void
    {
        $this->pendingLegacyRole = $role;
    }

    public function canManageUsers(): bool
    {
        return $this->can(PermissionName::USERS_VIEW->value);
    }

    public function canManageMasterData(): bool
    {
        return $this->canAny([
            PermissionName::AREAS_CREATE->value,
            PermissionName::AREAS_UPDATE->value,
            PermissionName::EMPLOYEES_CREATE->value,
            PermissionName::EMPLOYEES_UPDATE->value,
            PermissionName::CUSTOMERS_CREATE->value,
            PermissionName::CUSTOMERS_UPDATE->value,
            PermissionName::PRODUCTS_CREATE->value,
            PermissionName::PRODUCTS_UPDATE->value,
            PermissionName::DISTRIBUTION_ROUTES_CREATE->value,
            PermissionName::DISTRIBUTION_ROUTES_UPDATE->value,
            PermissionName::VEHICLES_CREATE->value,
            PermissionName::VEHICLES_UPDATE->value,
            PermissionName::WAREHOUSES_CREATE->value,
            PermissionName::WAREHOUSES_UPDATE->value,
        ]);
    }

    public function canManageInventory(): bool
    {
        return $this->canAny([
            PermissionName::STOCK_BALANCES_VIEW->value,
            PermissionName::STOCK_MOVEMENTS_VIEW->value,
            PermissionName::PRODUCTS_VIEW->value,
            PermissionName::WAREHOUSES_VIEW->value,
        ]);
    }

    public function canManageDistribution(): bool
    {
        return $this->canAny([
            PermissionName::DISTRIBUTION_ROUTES_VIEW->value,
            PermissionName::VEHICLES_VIEW->value,
            PermissionName::VEHICLE_LOADS_VIEW->value,
            PermissionName::VEHICLE_EXPENSES_VIEW->value,
        ]);
    }

    public function canManageSalesAndCollections(): bool
    {
        return $this->canAny([
            PermissionName::SALES_INVOICES_VIEW->value,
            PermissionName::SALES_RETURNS_VIEW->value,
            PermissionName::CUSTOMER_PAYMENTS_VIEW->value,
        ]);
    }

    public function canManageDailyClosings(): bool
    {
        return $this->can(PermissionName::DAILY_CLOSINGS_VIEW->value);
    }
}
