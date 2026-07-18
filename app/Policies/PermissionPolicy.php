<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;
use App\Services\Authorization\AccessScopeService;
use Illuminate\Database\Eloquent\Model;

abstract class PermissionPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isActive() ? null : false;
    }

    protected const VIEW_ANY = null;
    protected const VIEW = null;
    protected const CREATE = null;
    protected const UPDATE = null;
    protected const DELETE = null;
    protected const RESTORE = null;
    protected const FORCE_DELETE = null;
    protected const REPLICATE = null;
    protected const REORDER = null;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, static::VIEW_ANY);
    }

    public function view(User $user, Model $record): bool
    {
        return $this->allowsRecord(
            $user,
            $record,
            static::VIEW ?? static::VIEW_ANY,
        );
    }

    public function create(User $user): bool
    {
        return $this->allows($user, static::CREATE);
    }

    public function update(User $user, Model $record): bool
    {
        return $this->allowsMutation($user, $record, static::UPDATE);
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->allowsRecord($user, $record, static::DELETE);
    }

    public function deleteAny(User $user): bool
    {
        return $this->allows($user, static::DELETE);
    }

    public function restore(User $user, Model $record): bool
    {
        return $this->allowsRecord($user, $record, static::RESTORE);
    }

    public function restoreAny(User $user): bool
    {
        return $this->allows($user, static::RESTORE);
    }

    public function forceDelete(User $user, Model $record): bool
    {
        return $this->allowsRecord($user, $record, static::FORCE_DELETE);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->allows($user, static::FORCE_DELETE);
    }

    public function replicate(User $user, Model $record): bool
    {
        return $this->allowsRecord($user, $record, static::REPLICATE);
    }

    public function reorder(User $user): bool
    {
        return $this->allows($user, static::REORDER);
    }

    protected function allows(User $user, ?PermissionName $permission): bool
    {
        return $permission !== null
            && $user->can($permission->value);
    }

    protected function allowsRecord(
        User $user,
        Model $record,
        ?PermissionName $permission,
    ): bool {
        return $this->allows($user, $permission)
            && app(AccessScopeService::class)->allows($user, $record);
    }

    protected function allowsMutation(
        User $user,
        Model $record,
        ?PermissionName $permission,
    ): bool {
        $accessScopes = app(AccessScopeService::class);

        return $this->allows($user, $permission)
            && $accessScopes->allows($user, $record)
            && $accessScopes->allowsAttributes($user, $record);
    }
}
