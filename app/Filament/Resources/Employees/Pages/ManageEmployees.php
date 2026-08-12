<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Resources\Employees\EmployeeResource;
use App\Services\Imports\Excel\EmployeeExcelImportService;
use App\Services\Imports\Excel\EmployeeExcelTemplateService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ManageEmployees extends ManageRecords
{
    protected static string $resource = EmployeeResource::class;

    public bool $employeeExcelImportReady = false;

    public function getHeading(): string
    {
        return 'الموظفون والربط التشغيلي';
    }

    public function getSubheading(): ?string
    {
        return 'إدارة الهوية الوظيفية وربط الموظف بحساب مطابق للدور، مع استخدام الموظف مصدرًا للنطاقات الميدانية المشتقة.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => EmployeeResource::canCreate())
                ->label('إضافة موظف')
                ->icon('heroicon-o-user-plus')
                ->modalHeading('إضافة موظف')
                ->modalDescription('حدد نوع الموظف أولًا، ثم اربطه بحساب يحمل الدور المطابق عند الحاجة.')
                ->slideOver(),

            Action::make('downloadEmployeeExcelTemplate')
                ->visible(fn (): bool => EmployeeResource::canCreate())
                ->label('تحميل قالب Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => app(EmployeeExcelTemplateService::class)->download()),

            Action::make('importEmployeesExcel')
                ->visible(fn (): bool => EmployeeResource::canCreate())
                ->label('استيراد Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->modalHeading('استيراد الموظفين من Excel')
                ->modalDescription('ارفع قالب .xlsx فقط. يتم التحقق من رمز الموظف ونوعه وربط حساب المستخدم بالبريد الإلكتروني والدور المطابق قبل إنشاء أي سجل.')
                ->modalWidth('7xl')
                ->mountUsing(function (Schema $schema): void {
                    $this->employeeExcelImportReady = false;
                    $schema->fill();
                })
                ->modalSubmitAction(fn (Action $action): Action => $action
                    ->label('استيراد الملف')
                    ->disabled(fn (): bool => ! $this->employeeExcelImportReady))
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
                            $this->employeeExcelImportReady = false;

                            if (! $state instanceof TemporaryUploadedFile) {
                                return;
                            }

                            $analysis = app(EmployeeExcelImportService::class)->analyze(
                                $state->getRealPath(),
                                $state->getClientOriginalName(),
                            );

                            $this->employeeExcelImportReady = (bool) ($analysis['valid'] ?? false);
                        })
                        ->helperText('يسمح فقط بملفات .xlsx حتى 10 MB. user_email اختياري، وإذا تم تحديده يجب أن يطابق نوع الموظف وأن لا يكون مرتبطًا بموظف آخر.'),

                    View::make('filament.imports.employee-excel-preview'),
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

                    $result = app(EmployeeExcelImportService::class)->import(
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
                        ->title('تم استيراد الموظفين بنجاح')
                        ->body('تمت إضافة '.number_format($result['imported_count']).' موظف من ملف Excel.')
                        ->send();
                }),
        ];
    }
}
