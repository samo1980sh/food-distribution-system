<?php

namespace App\Filament\Resources\CustomerPayments\Pages;

use App\Enums\OperationSource;
use App\Filament\Resources\CustomerPayments\CustomerPaymentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCustomerPayments extends ManageRecords
{
    protected static string $resource = CustomerPaymentResource::class;

    public function getHeading(): string
    {
        return 'تحصيلات العملاء';
    }

    public function getSubheading(): ?string
    {
        return 'راجع التحصيلات الميدانية الواردة من التطبيق، أو سجّل تحصيلًا مكتبيًا أو مصرفيًا واضح المصدر.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('تسجيل تحصيل مكتبي')
                ->icon('heroicon-o-plus')
                ->modalHeading('تسجيل تحصيل مكتبي')
                ->modalDescription('استخدم هذا المسار للتحصيل الذي تم في المكتب أو عبر البنك. التحصيل الميداني يسجله المندوب من التطبيق.')
                ->slideOver()
                ->mutateDataUsing(function (array $data): array {
                    $data['operation_source'] = OperationSource::OFFICE;
                    $data['administrative_reason'] = trim((string) ($data['administrative_reason'] ?? ''))
                        ?: 'تحصيل مكتبي من لوحة الإدارة';

                    return $data;
                })
                ->visible(fn (): bool => CustomerPaymentResource::canCreate()),
        ];
    }
}
