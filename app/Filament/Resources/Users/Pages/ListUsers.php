<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    public function getHeading(): string
    {
        return 'المستخدمون والأدوار ونطاقات الوصول';
    }

    public function getSubheading(): ?string
    {
        return 'إدارة الحسابات من صفحات كاملة، مع عرض الدور والموظف المرتبط والنطاق الفعلي قبل تعديل أي صلاحية تشغيلية.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('إضافة مستخدم')
                ->icon('heroicon-o-user-plus')
                ->visible(fn (): bool => UserResource::canCreate()),
        ];
    }
}
