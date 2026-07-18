<?php

namespace App\Filament\Resources\Warehouses\Pages;

use App\Filament\Resources\Warehouses\WarehouseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageWarehouses extends ManageRecords
{
    protected static string $resource = WarehouseResource::class;

    public function getHeading(): string
    {
        return 'المستودعات ومخازن السيارات';
    }

    public function getSubheading(): ?string
    {
        return 'إدارة هيكل المستودعات والربط الفريد بين السيارة ومستودعها، دون تعديل الأرصدة من هذه الشاشة.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('إضافة مستودع')
                ->icon('heroicon-o-plus')
                ->modalHeading('إضافة مستودع')
                ->modalDescription('حدد نوع المستودع، واربط السيارة فقط عندما يكون المستودع متنقلًا.')
                ->slideOver()
                ->visible(fn (): bool => WarehouseResource::canManageWarehouseStructure()),
        ];
    }
}
