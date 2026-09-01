<?php

namespace App\Filament\Resources\FieldOperationalDayOverrides\Pages;

use App\Filament\Resources\FieldOperationalDayOverrides\FieldOperationalDayOverrideResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageFieldOperationalDayOverrides extends ManageRecords
{
    protected static string $resource = FieldOperationalDayOverrideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('إضافة تشغيل استثنائي')
                ->visible(fn (): bool => FieldOperationalDayOverrideResource::canCreate()),
        ];
    }
}
