<?php

namespace App\Filament\Resources\VehicleLoads\Pages;

use App\Filament\Resources\VehicleLoads\Actions\VehicleLoadActions;
use App\Filament\Resources\VehicleLoads\VehicleLoadResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVehicleLoad extends ViewRecord
{
    protected static string $resource = VehicleLoadResource::class;

    public function getHeading(): string
    {
        return 'أمر التحميل '.$this->record->load_number;
    }

    public function getSubheading(): ?string
    {
        return 'عرض موحد لمسار التحميل والمواد والتكلفة وسجل الاعتماد.';
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label('تعديل المسودة')
                ->visible(fn (): bool => auth()->user()?->can('update', $this->record) === true),
            VehicleLoadActions::approve(),
            VehicleLoadActions::cancel(),
            VehicleLoadActions::print(),
        ];
    }
}
