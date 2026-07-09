<?php

namespace App\Filament\Resources\VehicleExpenses\Pages;

use App\Filament\Resources\VehicleExpenses\VehicleExpenseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageVehicleExpenses extends ManageRecords
{
    protected static string $resource = VehicleExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('إضافة مصروف سيارة')
                ->modalHeading('إضافة مصروف سيارة')
                ->slideOver(),
        ];
    }
}