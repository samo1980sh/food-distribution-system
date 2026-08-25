<?php

namespace App\Filament\Resources\VehicleLoads\Pages;

use App\Filament\Resources\VehicleLoads\VehicleLoadResource;
use App\Support\Filament\AdminOperationalDriverGuard;
use Filament\Resources\Pages\CreateRecord;

class CreateVehicleLoad extends CreateRecord
{
    protected static string $resource = VehicleLoadResource::class;

    public function getHeading(): string
    {
        return 'إنشاء أمر تحميل سيارة';
    }

    public function getSubheading(): ?string
    {
        return 'حدد السيارة والمستودع المصدر والمواد، ثم احفظ الأمر كمسودة لمراجعته قبل نقل المخزون.';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return AdminOperationalDriverGuard::sanitize($data);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
