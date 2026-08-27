<?php

namespace App\Filament\Resources\PurchaseReceipts\Pages;

use App\Filament\Resources\PurchaseReceipts\PurchaseReceiptResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePurchaseReceipts extends ManageRecords
{
    protected static string $resource = PurchaseReceiptResource::class;

    public function getHeading(): string
    {
        return 'سجل استلامات المشتريات';
    }

    public function getSubheading(): ?string
    {
        return 'سجل قراءة فقط للاستلامات التي رُحلت فعليًا إلى المخزون وربطت بحركات Stock Receipt.';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
