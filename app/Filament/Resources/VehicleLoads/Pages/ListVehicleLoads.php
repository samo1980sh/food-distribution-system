<?php

namespace App\Filament\Resources\VehicleLoads\Pages;

use App\Filament\Resources\VehicleLoads\VehicleLoadResource;
use App\Models\VehicleLoad;
use App\Services\Distribution\VehicleLoadService;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class ListVehicleLoads extends ListRecords
{
    protected static string $resource = VehicleLoadResource::class;

    public function getHeading(): string
    {
        return 'أوامر تحميل السيارات';
    }

    public function getSubheading(): ?string
    {
        return 'إدارة نقل المواد من المستودع المركزي إلى مستودعات السيارات، مع متابعة التكلفة وحالة الاعتماد.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make('createAndApprove')
                ->label('حفظ واعتماد الحمولة')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->modalHeading('إنشاء واعتماد أمر تحميل سيارة')
                ->modalDescription('سيتم حفظ الأمر ثم فحص الرصيد الصالح واعتماده مباشرة. إذا تعذر الاعتماد، يبقى الأمر محفوظًا كمسودة بدون أي حركة مخزون.')
                ->modalSubmitActionLabel('حفظ واعتماد الحمولة')
                ->createAnother(false)
                ->visible(fn (): bool => VehicleLoadResource::canCreate()
                    && auth()->user()?->can('vehicle_loads.approve') === true)
                ->successNotification(null)
                ->after(function (VehicleLoad $record): void {
                    try {
                        Gate::authorize('approve', $record);
                        app(VehicleLoadService::class)->approve($record);
                        $record->refresh();

                        Notification::make()
                            ->title('تم حفظ واعتماد أمر التحميل')
                            ->body('تم فحص الرصيد ونقل الكميات إلى مستودع السيارة بنجاح.')
                            ->success()
                            ->send();
                    } catch (AuthorizationException|RuntimeException $exception) {
                        $record->refresh();

                        Notification::make()
                            ->title('تم حفظ أمر التحميل كمسودة')
                            ->body('تعذر الاعتماد المباشر: '.$exception->getMessage().' يمكنك معالجة السبب ثم اعتماد المسودة لاحقًا.')
                            ->warning()
                            ->persistent()
                            ->send();
                    }
                })
                ->slideOver(),

            CreateAction::make('createDraft')
                ->label('حفظ كمسودة')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->modalHeading('إنشاء أمر تحميل سيارة كمسودة')
                ->modalSubmitActionLabel('حفظ كمسودة')
                ->createAnother(false)
                ->visible(fn (): bool => VehicleLoadResource::canCreate())
                ->successNotificationTitle('تم حفظ أمر التحميل كمسودة')
                ->slideOver(),
        ];
    }
}
