<?php

namespace App\Filament\Resources\Units\Pages;

use App\Filament\Resources\Units\UnitResource;
use App\Services\Imports\Excel\UnitExcelImportService;
use App\Services\Imports\Excel\UnitExcelTemplateService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ManageUnits extends ManageRecords
{
    protected static string $resource = UnitResource::class;

    public bool $unitExcelImportReady = false;

    public function getHeading(): string
    {
        return 'وحدات القياس';
    }

    public function getSubheading(): ?string
    {
        return 'إدارة وحدات المنتجات والاختصارات المستخدمة في الكميات والتقارير مع المحافظة على الروابط التاريخية.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => UnitResource::canCreate())
                ->label('إضافة وحدة')
                ->icon('heroicon-o-plus')
                ->modalHeading('إضافة وحدة قياس')
                ->modalDescription('أدخل الرمز والاسم والاختصار المستخدم مع المنتجات.')
                ->slideOver(),

            Action::make('downloadUnitExcelTemplate')
                ->visible(fn (): bool => UnitResource::canCreate())
                ->label('تحميل قالب Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => app(UnitExcelTemplateService::class)->download()),

            Action::make('importUnitsExcel')
                ->visible(fn (): bool => UnitResource::canCreate())
                ->label('استيراد Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->modalHeading('استيراد وحدات القياس من Excel')
                ->modalDescription('ارفع قالب .xlsx فقط. سيُفحص الملف كاملًا قبل الحفظ، ولن يُستورد أي صف إذا وُجد خطأ واحد.')
                ->modalWidth('5xl')
                ->mountUsing(function (Schema $schema): void {
                    $this->unitExcelImportReady = false;
                    $schema->fill();
                })
                ->modalSubmitAction(fn (Action $action): Action => $action
                    ->label('استيراد الملف')
                    ->disabled(fn (): bool => ! $this->unitExcelImportReady))
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
                            $this->unitExcelImportReady = false;

                            if (! $state instanceof TemporaryUploadedFile) {
                                return;
                            }

                            $analysis = app(UnitExcelImportService::class)->analyze(
                                $state->getRealPath(),
                                $state->getClientOriginalName(),
                            );

                            $this->unitExcelImportReady = (bool) ($analysis['valid'] ?? false);
                        })
                        ->helperText('يسمح فقط بملفات .xlsx حتى 10 MB. استخدم القالب المعتمد لتجنب أخطاء الأعمدة.'),

                    View::make('filament.imports.unit-excel-preview'),
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

                    $result = app(UnitExcelImportService::class)->import(
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
                        ->title('تم استيراد وحدات القياس بنجاح')
                        ->body('تمت إضافة '.number_format($result['imported_count']).' وحدة من ملف Excel.')
                        ->send();
                }),
        ];
    }
}
