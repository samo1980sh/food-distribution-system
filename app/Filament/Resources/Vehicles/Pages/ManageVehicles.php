<?php

namespace App\Filament\Resources\Vehicles\Pages;

use App\Filament\Resources\Vehicles\VehicleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageVehicles extends ManageRecords
{
    protected static string $resource = VehicleResource::class;

    public function getHeading(): string
    {
        return 'أسطول السيارات';
    }

    public function getSubheading(): ?string
    {
        return 'إدارة هوية السيارة وحالتها وسعتها ووثائقها، مع إبقاء الربط بالمستودعات والخطوط محميًا بالسياق التشغيلي.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => VehicleResource::canCreate())
                ->label('إضافة سيارة')
                ->icon('heroicon-o-plus')
                ->modalHeading('إضافة سيارة')
                ->modalDescription('أدخل بيانات السيارة والسعة والعداد وتواريخ الوثائق.')
                ->slideOver(),
        ];
    }
}
