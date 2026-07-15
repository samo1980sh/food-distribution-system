<?php

namespace App\Support\Authorization;

use App\Models\User;
use App\Services\Authorization\AccessScopeService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ScopedModelObserver
{
    public function saving(Model $model): void
    {
        $user = Auth::user();

        if (! $user instanceof User || app(AccessScopeService::class)->allowsAttributes($user, $model)) {
            return;
        }

        throw new AuthorizationException(
            'لا يمكن حفظ العملية لأنها تحتوي بيانات خارج نطاق وصول المستخدم.'
        );
    }
}
