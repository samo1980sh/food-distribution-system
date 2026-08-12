<?php

namespace App\Filament\Resources\Vehicles\Pages;

use App\Filament\Resources\Vehicles\VehicleResource;
use App\Services\Imports\Excel\VehicleExcelImportService;
use App\Services\Imports\Excel\VehicleExcelTemplateService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ManageVehicles extends ManageRecords
{
    protected static string $resource = VehicleResource::class;

    public bool $vehicleExcelImportReady = false;

    public function getHeading(): string
    {
        return 'أسطول السيارات';
    }

    public function getSubheading(): ?string
    {
        return 'إدارة هوية السيارة وحالتها وسعتها ووثائقها، مع إبقاء الربط بالمستودعات والخطوط محميًا بالسياق التشغيلي.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => VehicleResource::canCreate())
                ->label('إضافة سيارة')
                ->icon('heroicon-o-plus')
                ->modalHeading('إضافة سيارة')
                ->modalDescription('أدخل بيانات السيارة والسعة والعداد وتواريخ الوثائق.')
                ->slideOver(),

            Action::make('downloadVehicleExcelTemplate')
                ->visible(fn (): bool => VehicleResource::canCreate())
                ->label('تحميل قالب Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => app(VehicleExcelTemplateService::class)->download()),

            Action::make('importVehiclesExcel')
                ->visible(fn (): bool => VehicleResource::canCreate())
                ->label('استيراد Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->modalHeading('استيراد السيارات من Excel')
                ->modalDescription('ارفع قالب .xlsx فقط. يتم فحص جميع السيارات واللوحات والسعات والعدادات والتواريخ قبل إضافة أي سجل.')
                ->modalWidth('7xl')
                ->mountUsing(function (Schema $schema): void {
                    $this->vehicleExcelImportReady = false;
                    $schema->fill();
                })
                ->modalSubmitAction(fn (Action $action): Action => $action
                    ->label('استيراد الملف')
                    ->disabled(fn (): bool => ! $this->vehicleExcelImportReady))
                ->schema([
                    FileUpload::make('excel_file')
                        ->label('ملف Excel (.xlsx)')
                        ->required()
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
                        ->maxSize(10240)
                        ->storeFiles(false)
                        ->previewable(false)
                        ->live()
                        ->afterStateUpdated(function (mixed $state): void {
                            $this->vehicleExcelImportReady = false;

                            if (! $state instanceof TemporaryUploadedFile) {
                                return;
                            }

                            $analysis = app(VehicleExcelImportService::class)->analyze(
                                $state->getRealPath(),
                                $state->getClientOriginalName(),
                            );

                            $this->vehicleExcelImportReady = (bool) ($analysis['valid'] ?? false);
                        })
                        ->helperText('يسمح فقط بملفات .xlsx حتى 10 MB. استخدم YYYY-MM-DD للتواريخ، ويمكن ترك الحقول الاختيارية فارغة.'),

                    View::make('filament.imports.vehicle-excel-preview'),
                ])
                ->action(function (array $data, Action $action): void {
                    $file = $data['excel_file'] ?? null;

                    if (! $file instanceof TemporaryUploadedFile) {
                        Notification::make()
                            ->danger()
                            ->title('تعذر قراءة الملف')
                            ->body('أعد اختيار ملف Excel بصيغة .xlsx ثم حاول مرة أخرى.')
                            ->send();

                        $action->halt();

                        return;
                    }

                    $result = app(VehicleExcelImportService::class)->import(
                        $file->getRealPath(),
                        $file->getClientOriginalName(),
                    );

                    if (! $result['valid']) {
                        Notification::make()
                            ->danger()
                            ->title('لم يتم استيراد أي سجل')
                            ->body(implode("\n", array_slice($result['errors'], 0, 5)))
                            ->persistent()
                            ->send();

                        $action->halt();

                        return;
                    }

                    Notification::make()
                        ->success()
                        ->title('تم استيراد السيارات بنجاح')
                        ->body('تمت إضافة '.number_format($result['imported_count']).' سيارة من ملف Excel.')
                        ->send();
                }),
        ];
    }
}
