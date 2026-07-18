<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Resources\Employees\EmployeeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEmployees extends ManageRecords
{
    protected static string $resource = EmployeeResource::class;

    public function getHeading(): string
    {
        return 'الموظفون والربط التشغيلي';
    }

    public function getSubheading(): ?string
    {
        return 'إدارة الهوية الوظيفية وربط الموظف بحساب مطابق للدور، مع استخدام الموظف مصدرًا للنطاقات الميدانية المشتقة.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => EmployeeResource::canCreate())
                ->label('إضافة موظف')
                ->icon('heroicon-o-user-plus')
                ->modalHeading('إضافة موظف')
                ->modalDescription('حدد نوع الموظف أولًا، ثم اربطه بحساب يحمل الدور المطابق عند الحاجة.')
                ->slideOver(),
        ];
    }
}
