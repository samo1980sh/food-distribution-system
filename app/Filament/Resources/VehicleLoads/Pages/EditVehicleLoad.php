<?php

namespace App\Filament\Resources\VehicleLoads\Pages;

use App\Filament\Resources\VehicleLoads\Actions\VehicleLoadActions;
use App\Filament\Resources\VehicleLoads\VehicleLoadResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditVehicleLoad extends EditRecord
{
    protected static string $resource = VehicleLoadResource::class;

    public function getHeading(): string
    {
        return 'تعديل أمر التحميل '.$this->record->load_number;
    }

    public function getSubheading(): ?string
    {
        return 'يمكن تعديل المسودة فقط. راجع السيارة والمستودعين والمواد قبل الاعتماد.';
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->label('عرض التفاصيل'),
            VehicleLoadActions::print(),
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
