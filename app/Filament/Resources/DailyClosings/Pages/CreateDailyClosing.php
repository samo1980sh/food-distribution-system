<?php

namespace App\Filament\Resources\DailyClosings\Pages;

use App\Filament\Resources\DailyClosings\DailyClosingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDailyClosing extends CreateRecord
{
    protected static string $resource = DailyClosingResource::class;

    public function getHeading(): string
    {
        return 'إنشاء مسودة إغلاق يومي';
    }

    public function getSubheading(): ?string
    {
        return 'حدد نطاق الإغلاق وأدخل النقد الفعلي، ثم احفظ المسودة لتحديث الملخص الدفتري وإجراء جرد المواد.';
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
