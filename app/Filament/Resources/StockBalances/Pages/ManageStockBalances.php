<?php

namespace App\Filament\Resources\StockBalances\Pages;

use App\Filament\Resources\StockBalances\StockBalanceResource;
use App\Services\Inventory\WarehouseStockAlertService;
use Filament\Resources\Pages\ManageRecords;

class ManageStockBalances extends ManageRecords
{
    protected static string $resource = StockBalanceResource::class;

    public function getHeading(): string
    {
        return 'المخزون الحالي';
    }

    public function getSubheading(): ?string
    {
        $summary = app(WarehouseStockAlertService::class)->mainWarehouseSummary();

        return 'الكميات الحالية الفعلية حسب المستودع والمنتج والتشغيلة والصلاحية. '
            .'تنبيهات المستودع الرئيسي: '
            .$summary['out_of_stock'].' نافد، '
            .$summary['low_stock'].' منخفض.';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}