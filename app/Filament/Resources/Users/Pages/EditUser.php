<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Actions\UserActions;
use App\Filament\Resources\Users\UserResource;
use App\Services\Authorization\UserScopeAssignmentService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    public function getHeading(): string
    {
        return 'تعديل حساب '.$this->record->name;
    }

    public function getSubheading(): ?string
    {
        return 'تغيير الدور يعيد تطبيع نطاقات الوصول غير المناسبة تلقائيًا، وتعطيل الحساب يلغي جلسات الجوال.';
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->label('عرض الحساب والنطاق'),
            UserActions::activate(),
            UserActions::deactivate(),
        ];
    }

    protected function afterSave(): void
    {
        app(UserScopeAssignmentService::class)->normalize($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
