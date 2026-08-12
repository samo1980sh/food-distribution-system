<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Services\Imports\Excel\CustomerExcelImportService;
use App\Services\Imports\Excel\CustomerExcelTemplateService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ManageCustomers extends ManageRecords
{
    protected static string $resource = CustomerResource::class;

    public bool $customerExcelImportReady = false;

    public function getHeading(): string
    {
        return 'دليل العملاء';
    }

    public function getSubheading(): ?string
    {
        return 'إدارة بيانات العميل والتوزيع والسياسة الائتمانية من مودال جانبي سريع مع المحافظة على السجل المالي.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => CustomerResource::canCreate())
                ->label('إضافة عميل')
                ->icon('heroicon-o-plus')
                ->modalHeading('إضافة عميل')
                ->modalDescription('أدخل الهوية والموقع وخط التوزيع والسياسة الائتمانية قبل حفظ العميل.')
                ->slideOver(),

            Action::make('downloadCustomerExcelTemplate')
                ->visible(fn (): bool => CustomerResource::canCreate())
                ->label('تحميل قالب Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => app(CustomerExcelTemplateService::class)->download()),

            Action::make('importCustomersExcel')
                ->visible(fn (): bool => CustomerResource::canCreate())
                ->label('استيراد Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->modalHeading('استيراد العملاء من Excel')
                ->modalDescription('ارفع قالب .xlsx فقط. يتم التحقق من بيانات العميل والمنطقة وخط التوزيع والسياسة الائتمانية قبل إنشاء أي عميل.')
                ->modalWidth('7xl')
                ->mountUsing(function (Schema $schema): void {
                    $this->customerExcelImportReady = false;
                    $schema->fill();
                })
                ->modalSubmitAction(fn (Action $action): Action => $action
                    ->label('استيراد الملف')
                    ->disabled(fn (): bool => ! $this->customerExcelImportReady))
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
                            $this->customerExcelImportReady = false;

                            if (! $state instanceof TemporaryUploadedFile) {
                                return;
                            }

                            $analysis = app(CustomerExcelImportService::class)->analyze(
                                $state->getRealPath(),
                                $state->getClientOriginalName(),
                            );

                            $this->customerExcelImportReady = (bool) ($analysis['valid'] ?? false);
                        })
                        ->helperText('يسمح فقط بملفات .xlsx حتى 10 MB. استخدم أكواد المنطقة وخط التوزيع من القوائم المرجعية، ولا تستخدم أرقام قاعدة البيانات.'),

                    View::make('filament.imports.customer-excel-preview'),
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

                    $result = app(CustomerExcelImportService::class)->import(
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
                        ->title('تم استيراد العملاء بنجاح')
                        ->body('تمت إضافة '.number_format($result['imported_count']).' عميل من ملف Excel.')
                        ->send();
                }),
        ];
    }
}
