<?php

namespace App\Filament\Resources\DistributionRoutes\Pages;

use App\Filament\Resources\DistributionRoutes\DistributionRouteResource;
use App\Services\Imports\Excel\DistributionRouteExcelImportService;
use App\Services\Imports\Excel\DistributionRouteExcelTemplateService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ManageDistributionRoutes extends ManageRecords
{
    protected static string $resource = DistributionRouteResource::class;

    public bool $distributionRouteExcelImportReady = false;

    public function getHeading(): string
    {
        return 'خطوط التوزيع والفرق الميدانية';
    }

    public function getSubheading(): ?string
    {
        return 'ربط المنطقة والسيارة والسائق والمندوب وأيام الزيارة، مع منع أي سياق تشغيلي غير متطابق.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => DistributionRouteResource::canCreate())
                ->label('إضافة خط توزيع')
                ->icon('heroicon-o-plus')
                ->modalHeading('إضافة خط توزيع')
                ->modalDescription('حدد المنطقة وفريق الخط والسيارة وأيام الزيارة قبل الحفظ.')
                ->slideOver(),

            Action::make('downloadDistributionRouteExcelTemplate')
                ->visible(fn (): bool => DistributionRouteResource::canCreate())
                ->label('تحميل قالب Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => app(DistributionRouteExcelTemplateService::class)->download()),

            Action::make('importDistributionRoutesExcel')
                ->visible(fn (): bool => DistributionRouteResource::canCreate())
                ->label('استيراد Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->modalHeading('استيراد خطوط التوزيع من Excel')
                ->modalDescription('ارفع قالب .xlsx فقط. يتم التحقق من المنطقة والسيارة وأهلية السائق والمندوب وأيام الزيارة قبل إنشاء أي خط.')
                ->modalWidth('7xl')
                ->mountUsing(function (Schema $schema): void {
                    $this->distributionRouteExcelImportReady = false;
                    $schema->fill();
                })
                ->modalSubmitAction(fn (Action $action): Action => $action
                    ->label('استيراد الملف')
                    ->disabled(fn (): bool => ! $this->distributionRouteExcelImportReady))
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
                            $this->distributionRouteExcelImportReady = false;

                            if (! $state instanceof TemporaryUploadedFile) {
                                return;
                            }

                            $analysis = app(DistributionRouteExcelImportService::class)->analyze(
                                $state->getRealPath(),
                                $state->getClientOriginalName(),
                            );

                            $this->distributionRouteExcelImportReady = (bool) ($analysis['valid'] ?? false);
                        })
                        ->helperText('يسمح فقط بملفات .xlsx حتى 10 MB. استخدم أكواد الأعمال من القوائم المرجعية، واكتب visit_days مفصولة بفاصلة إنجليزية عند وجود أكثر من يوم.'),

                    View::make('filament.imports.distribution-route-excel-preview'),
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

                    $result = app(DistributionRouteExcelImportService::class)->import(
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
                        ->title('تم استيراد خطوط التوزيع بنجاح')
                        ->body('تمت إضافة '.number_format($result['imported_count']).' خط توزيع من ملف Excel.')
                        ->send();
                }),
        ];
    }
}
