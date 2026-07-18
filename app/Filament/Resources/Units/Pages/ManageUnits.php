<?php

namespace App\Filament\Resources\Units\Pages;

use App\Filament\Resources\Units\UnitResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageUnits extends ManageRecords
{
    protected static string $resource = UnitResource::class;

    public function getHeading(): string
    {
        return 'وحدات القياس';
    }

    public function getSubheading(): ?string
    {
        return 'إدارة وحدات المنتجات والاختصارات المستخدمة في الكميات والتقارير مع المحافظة على الروابط التاريخية.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => UnitResource::canCreate())
                ->label('إضافة وحدة')
                ->icon('heroicon-o-plus')
                ->modalHeading('إضافة وحدة قياس')
                ->modalDescription('أدخل الرمز والاسم والاختصار المستخدم مع المنتجات.')
                ->slideOver(),
        ];
    }
}
