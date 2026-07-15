<?php

namespace App\Filament\Resources\DistributionRoutes\Pages;

use App\Filament\Resources\DistributionRoutes\DistributionRouteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDistributionRoutes extends ManageRecords
{
    protected static string $resource = DistributionRouteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => DistributionRouteResource::canCreate())
                ->label('إضافة خط توزيع')
                ->modalHeading('إضافة خط توزيع')
                ->slideOver(),
        ];
    }
}