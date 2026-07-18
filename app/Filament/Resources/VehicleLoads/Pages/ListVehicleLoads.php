<?php

namespace App\Filament\Resources\VehicleLoads\Pages;

use App\Filament\Resources\VehicleLoads\VehicleLoadResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVehicleLoads extends ListRecords
{
    protected static string $resource = VehicleLoadResource::class;

    public function getHeading(): string
    {
        return 'أوامر تحميل السيارات';
    }

    public function getSubheading(): ?string
    {
        return 'إدارة نقل المواد من المستودع المركزي إلى مستودعات السيارات، مع متابعة التكلفة وحالة الاعتماد.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('أمر تحميل جديد')
                ->icon('heroicon-o-plus')
                ->visible(fn (): bool => VehicleLoadResource::canCreate()),
        ];
    }
}
