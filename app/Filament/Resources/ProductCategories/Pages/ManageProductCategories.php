<?php

namespace App\Filament\Resources\ProductCategories\Pages;

use App\Filament\Resources\ProductCategories\ProductCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageProductCategories extends ManageRecords
{
    protected static string $resource = ProductCategoryResource::class;

    public function getHeading(): string
    {
        return 'تصنيفات المنتجات';
    }

    public function getSubheading(): ?string
    {
        return 'تنظيم شجرة التصنيفات وترتيبها وحالتها من مودال جانبي سريع، دون حذف السجلات المرتبطة.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => ProductCategoryResource::canCreate())
                ->label('إضافة تصنيف')
                ->icon('heroicon-o-plus')
                ->modalHeading('إضافة تصنيف')
                ->modalDescription('حدد الرمز والاسم والتصنيف الأب وترتيب العرض.')
                ->slideOver(),
        ];
    }
}
