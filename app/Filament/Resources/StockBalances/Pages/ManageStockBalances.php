<?php

namespace App\Filament\Resources\StockBalances\Pages;

use App\Filament\Resources\StockBalances\StockBalanceResource;
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
        return 'الكميات الحالية الفعلية حسب المستودع والمنتج والتشغيلة والصلاحية، مع متوسط التكلفة وقيمة المخزون.';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}