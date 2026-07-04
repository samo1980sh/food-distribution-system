<?php

namespace App\Filament\Resources\DailyClosings\Pages;

use App\Filament\Resources\DailyClosings\DailyClosingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDailyClosings extends ManageRecords
{
    protected static string $resource = DailyClosingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('إضافة إغلاق يوم')
                ->modalHeading('إضافة إغلاق يوم')
                ->slideOver(),
        ];
    }
}