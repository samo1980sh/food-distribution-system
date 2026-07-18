<?php

namespace App\Filament\Resources\DailyClosings\Pages;

use App\Filament\Resources\DailyClosings\Actions\DailyClosingActions;
use App\Filament\Resources\DailyClosings\DailyClosingResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDailyClosing extends EditRecord
{
    protected static string $resource = DailyClosingResource::class;

    public function getHeading(): string
    {
        return 'جرد وتعديل الإغلاق '.$this->record->closing_number;
    }

    public function getSubheading(): ?string
    {
        return 'أدخل الجرد الفعلي لكل مادة وراجع النقد الفعلي والملاحظات، ثم احفظ المسودة قبل اعتمادها من صفحة التفاصيل.';
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->label('عرض المطابقة الكاملة'),
            DailyClosingActions::print(),
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
