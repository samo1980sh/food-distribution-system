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
        return 'مراجعة طلبات المرتجع الواردة من التطبيق والتحقق من جاهزيتها للاعتماد، مع إبقاء الإدخال الإداري للحالات الاستثنائية فقط.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('مرتجع إداري استثنائي')
                ->icon('heroicon-o-plus')
                ->visible(fn (): bool => SalesReturnResource::canCreate()),
        ];
    }
}
