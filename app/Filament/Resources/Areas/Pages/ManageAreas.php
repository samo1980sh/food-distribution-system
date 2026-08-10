<?php

namespace App\Filament\Resources\Areas\Pages;

use App\Filament\Resources\Areas\AreaResource;
use App\Services\Imports\Excel\AreaExcelImportService;
use App\Services\Imports\Excel\AreaExcelTemplateService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ManageAreas extends ManageRecords
{
    protected static string $resource = AreaResource::class;

    public bool $areaExcelImportReady = false;

    public function getHeading(): string
    {
        return 'المناطق الجغرافية';
    }

    public function getSubheading(): ?string
    {
        return 'إدارة المناطق المستخدمة في العملاء والخطوط ونطاقات الوصول، دون حذف السجلات التاريخية.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => AreaResource::canCreate())
                ->label('إضافة منطقة')
                ->icon('heroicon-o-plus')
                ->modalHeading('إضافة منطقة')
                ->modalDescription('أدخل رمزًا فريدًا واسم المنطقة والمدينة، ثم احفظها كمنطقة فعالة.')
                ->slideOver(),

            Action::make('downloadAreaExcelTemplate')
                ->visible(fn (): bool => AreaResource::canCreate())
                ->label('تحميل قالب Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => app(AreaExcelTemplateService::class)->download()),

            Action::make('importAreasExcel')
                ->visible(fn (): bool => AreaResource::canCreate())
                ->label('استيراد Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->modalHeading('استيراد المناطق من Excel')
                ->modalDescription('ارفع قالب .xlsx فقط. سيُفحص الملف كاملًا قبل الحفظ، ولن يُستورد أي صف إذا وُجد خطأ واحد.')
                ->modalWidth('5xl')
                ->mountUsing(function (Schema $schema): void {
                    $this->areaExcelImportReady = false;
                    $schema->fill();
                })
                ->modalSubmitAction(fn (Action $action): Action => $action
                    ->label('استيراد الملف')
                    ->disabled(fn (): bool => ! $this->areaExcelImportReady))
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
                            $this->areaExcelImportReady = false;

                            if (! $state instanceof TemporaryUploadedFile) {
                                return;
                            }

                            $analysis = app(AreaExcelImportService::class)->analyze(
                                $state->getRealPath(),
                                $state->getClientOriginalName(),
                            );

                            $this->areaExcelImportReady = (bool) ($analysis['valid'] ?? false);
                        })
                        ->helperText('يسمح فقط بملفات .xlsx حتى 10 MB. استخدم القالب المعتمد لتجنب أخطاء الأعمدة.'),

                    View::make('filament.imports.area-excel-preview'),
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

                    $result = app(AreaExcelImportService::class)->import(
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
                        ->title('تم استيراد المناطق بنجاح')
                        ->body('تمت إضافة '.number_format($result['imported_count']).' منطقة من ملف Excel.')
                        ->send();
                }),
        ];
    }
}
