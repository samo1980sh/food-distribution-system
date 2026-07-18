<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Services\Authorization\UserScopeAssignmentService;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    public function getHeading(): string
    {
        return 'إنشاء حساب مستخدم';
    }

    public function getSubheading(): ?string
    {
        return 'حدد بيانات الحساب والدور ونطاق الوصول المباشر، ثم راجع النطاق الفعلي من صفحة التفاصيل.';
    }

    protected function afterCreate(): void
    {
        app(UserScopeAssignmentService::class)->normalize($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
