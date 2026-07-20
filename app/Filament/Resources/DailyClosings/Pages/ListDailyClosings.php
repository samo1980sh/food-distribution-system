<?php

namespace App\Filament\Resources\DailyClosings\Pages;

use App\Filament\Resources\DailyClosings\DailyClosingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDailyClosings extends ListRecords
{
    protected static string $resource = DailyClosingResource::class;

    public function getHeading(): string
    {
        return 'مساحة الإغلاق اليومي';
    }

    public function getSubheading(): ?string
    {
        return 'إنشاء الإغلاق إداريًا بصورة مؤقتة، ثم مراجعة العهدة والجرد والصندوق واعتماد المطابقة.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('إغلاق إداري مؤقت')
                ->icon('heroicon-o-plus')
                ->visible(fn (): bool => DailyClosingResource::canCreate()),
        ];
    }
}
