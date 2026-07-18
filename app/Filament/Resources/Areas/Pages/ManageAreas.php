<?php

namespace App\Filament\Resources\Areas\Pages;

use App\Filament\Resources\Areas\AreaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAreas extends ManageRecords
{
    protected static string $resource = AreaResource::class;

    public function getHeading(): string
    {
        return 'المناطق الجغرافية';
    }

    public function getSubheading(): ?string
    {
        return 'إدارة المناطق المستخدمة في العملاء والخطوط ونطاقات الوصول، دون حذف السجلات التاريخية.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => AreaResource::canCreate())
                ->label('إضافة منطقة')
                ->icon('heroicon-o-plus')
                ->modalHeading('إضافة منطقة')
                ->modalDescription('أدخل رمزًا فريدًا واسم المنطقة والمدينة، ثم احفظها كمنطقة فعالة.')
                ->slideOver(),
        ];
    }
}
