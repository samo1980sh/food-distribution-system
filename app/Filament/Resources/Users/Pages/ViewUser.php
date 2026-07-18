<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Actions\UserActions;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    public function getHeading(): string
    {
        return 'حساب '.$this->record->name;
    }

    public function getSubheading(): ?string
    {
        return 'مراجعة موحدة للأدوار والموظف والتعيينات المباشرة ونطاق الوصول الفعلي وحالة الجلسات.';
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label('تعديل الحساب')
                ->visible(fn (): bool => auth()->user()?->can('update', $this->record) === true),
            UserActions::activate(),
            UserActions::deactivate(),
        ];
    }
}
