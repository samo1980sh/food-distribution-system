<?php

namespace App\Filament\Resources\VehicleLoads\Pages;

use App\Filament\Resources\VehicleLoads\VehicleLoadResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageVehicleLoads extends ManageRecords
{
    protected static string $resource = VehicleLoadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => auth()->user()?->canManageDistribution() === true)
                ->label('إضافة أمر تحميل')
                ->modalHeading('إضافة أمر تحميل سيارة')
                ->slideOver(),
        ];
    }
}