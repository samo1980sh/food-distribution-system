<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Services\Imports\Excel\ProductExcelImportService;
use App\Services\Imports\Excel\ProductExcelTemplateService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ManageProducts extends ManageRecords
{
    protected static string $resource = ProductResource::class;

    public bool $productExcelImportReady = false;

    public function getHeading(): string
    {
        return 'دليل المنتجات والأسعار';
    }

    public function getSubheading(): ?string
    {
        return 'إدارة هوية المنتج وأسعاره المرجعية وضوابط الصلاحية، بينما تبقى الأرصدة قابلة للتغيير من الحركات التشغيلية فقط.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => ProductResource::canCreate())
                ->label('إضافة منتج')
                ->icon('heroicon-o-plus')
                ->modalHeading('إضافة منتج')
                ->modalDescription('أدخل هوية المنتج والتصنيف والوحدة والأسعار وضوابط المخزون.')
                ->slideOver(),

            Action::make('downloadProductExcelTemplate')
                ->visible(fn (): bool => ProductResource::canCreate())
                ->label('تحميل قالب Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => app(ProductExcelTemplateService::class)->download()),

            Action::make('importProductsExcel')
                ->visible(fn (): bool => ProductResource::canCreate())
                ->label('استيراد Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->modalHeading('استيراد المنتجات من Excel')
                ->modalDescription('ارفع قالب .xlsx فقط. يتم ربط التصنيف والوحدة بواسطة code، وفحص الملف كاملًا قبل إضافة أي منتج.')
                ->modalWidth('7xl')
                ->mountUsing(function (Schema $schema): void {
                    $this->productExcelImportReady = false;
                    $schema->fill();
                })
                ->modalSubmitAction(fn (Action $action): Action => $action
                    ->label('استيراد الملف')
                    ->disabled(fn (): bool => ! $this->productExcelImportReady))
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
                            $this->productExcelImportReady = false;

                            if (! $state instanceof TemporaryUploadedFile) {
                                return;
                            }

                            $analysis = app(ProductExcelImportService::class)->analyze(
                                $state->getRealPath(),
                                $state->getClientOriginalName(),
                            );

                            $this->productExcelImportReady = (bool) ($analysis['valid'] ?? false);
                        })
                        ->helperText('يسمح فقط بملفات .xlsx حتى 10 MB. استخدم category_code و unit_code بدل أرقام ID.'),

                    View::make('filament.imports.product-excel-preview'),
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

                    $result = app(ProductExcelImportService::class)->import(
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
                        ->title('تم استيراد المنتجات بنجاح')
                        ->body('تمت إضافة '.number_format($result['imported_count']).' منتج من ملف Excel.')
                        ->send();
                }),
        ];
    }
}
