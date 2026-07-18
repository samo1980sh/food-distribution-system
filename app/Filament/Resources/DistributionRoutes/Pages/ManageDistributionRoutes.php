<?php

namespace App\Filament\Resources\DistributionRoutes\Pages;

use App\Filament\Resources\DistributionRoutes\DistributionRouteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDistributionRoutes extends ManageRecords
{
    protected static string $resource = DistributionRouteResource::class;

    public function getHeading(): string
    {
        return 'خطوط التوزيع والفرق الميدانية';
    }

    public function getSubheading(): ?string
    {
        return 'ربط المنطقة والسيارة والسائق والمندوب وأيام الزيارة، مع منع أي سياق تشغيلي غير متطابق.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => DistributionRouteResource::canCreate())
                ->label('إضافة خط توزيع')
                ->icon('heroicon-o-plus')
                ->modalHeading('إضافة خط توزيع')
                ->modalDescription('حدد المنطقة وفريق الخط والسيارة وأيام الزيارة قبل الحفظ.')
                ->slideOver(),
        ];
    }
}
