<?php

namespace App\Filament\Resources\SalesReturns\Pages;

use App\Filament\Resources\SalesReturns\Actions\SalesReturnActions;
use App\Filament\Resources\SalesReturns\SalesReturnResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSalesReturn extends ViewRecord
{
    protected static string $resource = SalesReturnResource::class;

    public function getHeading(): string
    {
        return 'مرتجع '.$this->record->return_number;
    }

    public function getSubheading(): ?string
    {
        return 'عرض موحد للفاتورة الأصلية والسياق التشغيلي والمواد والأثر المالي وسجل الاعتماد.';
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label('تعديل المسودة')
                ->visible(fn (): bool => auth()->user()?->can('update', $this->record) === true),
            SalesReturnActions::confirm(),
            SalesReturnActions::cancel(),
            SalesReturnActions::print(),
        ];
    }
}
