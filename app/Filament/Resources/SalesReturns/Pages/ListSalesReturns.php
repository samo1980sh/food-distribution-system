<?php

namespace App\Filament\Resources\SalesReturns\Pages;

use App\Filament\Resources\SalesReturns\SalesReturnResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSalesReturns extends ListRecords
{
    protected static string $resource = SalesReturnResource::class;

    public function getHeading(): string
    {
        return 'مرتجعات المبيعات';
    }

    public function getSubheading(): ?string
    {
        return 'إدارة المرتجعات من المسودة حتى الاعتماد أو الإلغاء، مع ربطها بالفاتورة الأصلية ومتابعة أثرها على المخزون والرصيد.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('مرتجع بيع جديد')
                ->icon('heroicon-o-plus')
                ->visible(fn (): bool => SalesReturnResource::canCreate()),
        ];
    }
}
