<?php

namespace App\Filament\Resources\VehicleExpenses\Pages;

use App\Filament\Resources\VehicleExpenses\VehicleExpenseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageVehicleExpenses extends ManageRecords
{
    protected static string $resource = VehicleExpenseResource::class;

    public function getHeading(): string
    {
        return 'مصاريف السيارات';
    }

    public function getSubheading(): ?string
    {
        return 'سجّل المصروفات بسرعة من المودال الجانبي، وراجع الإيصال والسياق التشغيلي من صفحة التفاصيل الكاملة.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('مصروف سيارة جديد')
                ->icon('heroicon-o-plus')
                ->modalHeading('إضافة مصروف سيارة')
                ->modalDescription('حدد السيارة والتاريخ والنوع والمبلغ، وأرفق صورة الإيصال إن توفرت.')
                ->slideOver()
                ->visible(fn (): bool => VehicleExpenseResource::canCreate()),
        ];
    }
}
