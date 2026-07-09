<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_SUPERVISOR = 'supervisor';
    public const ROLE_WAREHOUSE_KEEPER = 'warehouse_keeper';
    public const ROLE_ACCOUNTANT = 'accountant';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

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

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        return $this->hasAnyRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_MANAGER,
            self::ROLE_SUPERVISOR,
            self::ROLE_WAREHOUSE_KEEPER,
            self::ROLE_ACCOUNTANT,
        ]);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN);
    }

    public function canManageUsers(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canManageMasterData(): bool
    {
        return $this->hasAnyRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_MANAGER,
        ]);
    }

    public function canManageInventory(): bool
    {
        return $this->hasAnyRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_MANAGER,
            self::ROLE_WAREHOUSE_KEEPER,
        ]);
    }

    public function canManageDistribution(): bool
    {
        return $this->hasAnyRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_MANAGER,
            self::ROLE_SUPERVISOR,
            self::ROLE_WAREHOUSE_KEEPER,
        ]);
    }

    public function canManageSalesAndCollections(): bool
    {
        return $this->hasAnyRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_MANAGER,
            self::ROLE_SUPERVISOR,
            self::ROLE_ACCOUNTANT,
        ]);
    }

    public function canManageDailyClosings(): bool
    {
        return $this->hasAnyRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_MANAGER,
            self::ROLE_SUPERVISOR,
            self::ROLE_ACCOUNTANT,
        ]);
    }
}