<?php

namespace App\Filament\Resources\SalesReturns\Pages;

use App\Filament\Resources\SalesReturns\Actions\SalesReturnActions;
use App\Filament\Resources\SalesReturns\SalesReturnResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSalesReturn extends EditRecord
{
    protected static string $resource = SalesReturnResource::class;

    public function getHeading(): string
    {
        return 'تعديل المرتجع '.$this->record->return_number;
    }

    public function getSubheading(): ?string
    {
        return 'يمكن تعديل المسودة فقط. راجع الفاتورة الأصلية والكميات والمستودع قبل الاعتماد.';
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->label('عرض التفاصيل'),
            SalesReturnActions::print(),
            DeleteAction::make()
                ->label('حذف المسودة')
                ->visible(fn (): bool => auth()->user()?->can('delete', $this->record) === true),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
