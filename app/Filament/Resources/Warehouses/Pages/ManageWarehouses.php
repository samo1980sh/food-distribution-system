<?php

namespace App\Filament\Resources\Warehouses\Pages;

use App\Filament\Resources\Warehouses\WarehouseResource;
use App\Services\Imports\Excel\WarehouseExcelImportService;
use App\Services\Imports\Excel\WarehouseExcelTemplateService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ManageWarehouses extends ManageRecords
{
    protected static string $resource = WarehouseResource::class;

    public bool $warehouseExcelImportReady = false;

    public function getHeading(): string
    {
        return 'المستودعات ومخازن السيارات';
    }

    public function getSubheading(): ?string
    {
        return 'إدارة هيكل المستودعات والربط الفريد بين السيارة ومستودعها، دون تعديل الأرصدة من هذه الشاشة.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('إضافة مستودع')
                ->icon('heroicon-o-plus')
                ->modalHeading('إضافة مستودع')
                ->modalDescription('حدد نوع المستودع، واربط السيارة فقط عندما يكون المستودع متنقلًا.')
                ->slideOver()
                ->visible(fn (): bool => WarehouseResource::canManageWarehouseStructure()),

            Action::make('downloadWarehouseExcelTemplate')
                ->visible(fn (): bool => WarehouseResource::canManageWarehouseStructure())
                ->label('تحميل قالب Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => app(WarehouseExcelTemplateService::class)->download()),

            Action::make('importWarehousesExcel')
                ->visible(fn (): bool => WarehouseResource::canManageWarehouseStructure())
                ->label('استيراد Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->modalHeading('استيراد المستودعات من Excel')
                ->modalDescription('ارفع قالب .xlsx فقط. يتم فحص الأنواع والحالات وربط مستودعات السيارات بالسيارات الفعالة المتاحة قبل إنشاء أي سجل.')
                ->modalWidth('7xl')
                ->mountUsing(function (Schema $schema): void {
                    $this->warehouseExcelImportReady = false;
                    $schema->fill();
                })
                ->modalSubmitAction(fn (Action $action): Action => $action
                    ->label('استيراد الملف')
                    ->disabled(fn (): bool => ! $this->warehouseExcelImportReady))
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
                            $this->warehouseExcelImportReady = false;

                            if (! $state instanceof TemporaryUploadedFile) {
                                return;
                            }

                            $analysis = app(WarehouseExcelImportService::class)->analyze(
                                $state->getRealPath(),
                                $state->getClientOriginalName(),
                            );

                            $this->warehouseExcelImportReady = (bool) ($analysis['valid'] ?? false);
                        })
                        ->helperText('يسمح فقط بملفات .xlsx حتى 10 MB. اختر السيارة من القائمة فقط عندما يكون type = vehicle.'),

                    View::make('filament.imports.warehouse-excel-preview'),
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

                    $result = app(WarehouseExcelImportService::class)->import(
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
                        ->title('تم استيراد المستودعات بنجاح')
                        ->body('تمت إضافة '.number_format($result['imported_count']).' مستودع من ملف Excel.')
                        ->send();
                }),
        ];
    }
}
