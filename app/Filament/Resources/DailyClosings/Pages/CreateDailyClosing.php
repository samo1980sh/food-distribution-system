<?php

namespace App\Filament\Resources\DailyClosings\Pages;

use App\Enums\OperationSource;
use App\Filament\Resources\DailyClosings\DailyClosingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDailyClosing extends CreateRecord
{
    protected static string $resource = DailyClosingResource::class;

    public function getHeading(): string
    {
        return 'إنشاء إغلاق إداري مؤقت';
    }

    public function getSubheading(): ?string
    {
        return 'يبقى إنشاء الإغلاق من لوحة الإدارة مؤقتًا إلى أن تكتمل مساحة الإغلاق الميداني في التطبيق.';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['operation_source'] = OperationSource::OFFICE;
        $data['administrative_reason'] = trim((string) ($data['administrative_reason'] ?? ''));

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
