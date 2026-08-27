<?php

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Services\Purchasing\PurchaseOrderService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePurchaseOrders extends ManageRecords
{
    protected static string $resource = PurchaseOrderResource::class;

    public function getHeading(): string
    {
        return 'أوامر الشراء والاستلام';
    }

    public function getSubheading(): ?string
    {
        return 'المسودة لا تحرك المخزون. بعد الاعتماد يمكن تسجيل استلام جزئي أو كامل، وكل استلام يرحل إلى المخزون مرة واحدة.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => PurchaseOrderResource::canCreate())
                ->label('أمر شراء جديد')
                ->icon('heroicon-o-plus')
                ->modalHeading('إنشاء أمر شراء')
                ->slideOver()
                ->after(function (PurchaseOrder $record): void {
                    app(PurchaseOrderService::class)->recalculate($record);
                }),
        ];
    }
}
