<?php

namespace App\Filament\Resources\VehicleExpenses\Pages;

use App\Enums\OperationSource;
use App\Filament\Resources\VehicleExpenses\VehicleExpenseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageVehicleExpenses extends ManageRecords
{
    protected static string $resource = VehicleExpenseResource::class;

    public function getHeading(): string
    {
        return 'مصاريف السيارات';
    }

    public function getSubheading(): ?string
    {
        return 'راجع مصاريف السائقين الواردة من التطبيق واعتمدها أو ارفضها. الإدخال الإداري متاح فقط كاستثناء موثق.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('مصروف إداري استثنائي')
                ->icon('heroicon-o-plus')
                ->modalHeading('إضافة مصروف إداري استثنائي')
                ->modalDescription('استخدم هذا المسار فقط عند تعذر تسجيل المصروف من تطبيق السائق، مع توثيق السبب.')
                ->slideOver()
                ->mutateDataUsing(function (array $data): array {
                    $data['operation_source'] = OperationSource::ADMIN_EXCEPTION;
                    $data['administrative_reason'] = trim((string) ($data['administrative_reason'] ?? ''));

                    return $data;
                })
                ->visible(fn (): bool => VehicleExpenseResource::canCreate()),
        ];
    }
}
