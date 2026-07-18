<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageProducts extends ManageRecords
{
    protected static string $resource = ProductResource::class;

    public function getHeading(): string
    {
        return 'دليل المنتجات والأسعار';
    }

    public function getSubheading(): ?string
    {
        return 'إدارة هوية المنتج وأسعاره المرجعية وضوابط الصلاحية، بينما تبقى الأرصدة قابلة للتغيير من الحركات التشغيلية فقط.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => ProductResource::canCreate())
                ->label('إضافة منتج')
                ->icon('heroicon-o-plus')
                ->modalHeading('إضافة منتج')
                ->modalDescription('أدخل هوية المنتج والتصنيف والوحدة والأسعار وضوابط المخزون.')
                ->slideOver(),
        ];
    }
}
