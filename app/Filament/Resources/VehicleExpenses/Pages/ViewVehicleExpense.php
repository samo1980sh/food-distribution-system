<?php

namespace App\Filament\Resources\VehicleExpenses\Pages;

use App\Filament\Resources\VehicleExpenses\Actions\VehicleExpenseActions;
use App\Filament\Resources\VehicleExpenses\VehicleExpenseResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVehicleExpense extends ViewRecord
{
    protected static string $resource = VehicleExpenseResource::class;

    public function getHeading(): string
    {
        return 'مصروف '.$this->record->expense_number;
    }

    public function getSubheading(): ?string
    {
        return 'عرض موحد للمبلغ والإيصال والسيارة والسياق التشغيلي وسجل الاعتماد أو الرفض.';
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label('تعديل المصروف')
                ->modalHeading('تعديل مصروف سيارة')
                ->slideOver()
                ->visible(fn (): bool => auth()->user()?->can('update', $this->record) === true),
            VehicleExpenseActions::approve(),
            VehicleExpenseActions::reject(),
            VehicleExpenseActions::print(),
        ];
    }
}
