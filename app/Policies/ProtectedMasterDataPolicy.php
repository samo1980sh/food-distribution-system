<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

abstract class ProtectedMasterDataPolicy extends PermissionPolicy
{
    public function delete(User $user, Model $record): bool
    {
        return $user->isSuperAdmin() && parent::delete($user, $record);
    }

    public function deleteAny(User $user): bool
    {
        return $user->isSuperAdmin() && parent::deleteAny($user);
    }

    public function forceDelete(User $user, Model $record): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }
}
