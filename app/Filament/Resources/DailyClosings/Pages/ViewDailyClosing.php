<?php

namespace App\Filament\Resources\DailyClosings\Pages;

use App\Filament\Resources\DailyClosings\Actions\DailyClosingActions;
use App\Filament\Resources\DailyClosings\DailyClosingResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDailyClosing extends ViewRecord
{
    protected static string $resource = DailyClosingResource::class;

    public function getHeading(): string
    {
        return 'الإغلاق اليومي '.$this->record->closing_number;
    }

    public function getSubheading(): ?string
    {
        return 'مساحة موحدة لمراجعة المخزون الدفتري والجرد الفعلي والمبيعات والتحصيلات والمصاريف وفرق الصندوق.';
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label('إدخال الجرد وتعديل المسودة')
                ->icon('heroicon-o-pencil-square')
                ->visible(fn (): bool => auth()->user()?->can('update', $this->record) === true),
            DailyClosingActions::refreshTotals(),
            DailyClosingActions::confirm(),
            DailyClosingActions::cancel(),
            DailyClosingActions::print(),
        ];
    }
}
