<?php

namespace App\Filament\Resources\VehicleLoads\Pages;

use App\Filament\Resources\VehicleLoads\VehicleLoadResource;
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
        return 'حدد السيارة والمستودع المصدر والمواد. يمكنك حفظ الأمر كمسودة أو حفظه واعتماده مباشرة بعد فحص الرصيد.';
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
