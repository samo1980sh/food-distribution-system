<?php

namespace App\Filament\Resources\ProductCategories\Pages;

use App\Filament\Resources\ProductCategories\ProductCategoryResource;
use App\Services\Imports\Excel\ProductCategoryExcelImportService;
use App\Services\Imports\Excel\ProductCategoryExcelTemplateService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ManageProductCategories extends ManageRecords
{
    protected static string $resource = ProductCategoryResource::class;

    public bool $productCategoryExcelImportReady = false;

    public function getHeading(): string
    {
        return 'تصنيفات المنتجات';
    }

    public function getSubheading(): ?string
    {
        return 'تنظيم شجرة التصنيفات وترتيبها وحالتها من مودال جانبي سريع، دون حذف السجلات المرتبطة.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => ProductCategoryResource::canCreate())
                ->label('إضافة تصنيف')
                ->icon('heroicon-o-plus')
                ->modalHeading('إضافة تصنيف')
                ->modalDescription('حدد الرمز والاسم والتصنيف الأب وترتيب العرض.')
                ->slideOver(),

            Action::make('downloadProductCategoryExcelTemplate')
                ->visible(fn (): bool => ProductCategoryResource::canCreate())
                ->label('تحميل قالب Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => app(ProductCategoryExcelTemplateService::class)->download()),

            Action::make('importProductCategoriesExcel')
                ->visible(fn (): bool => ProductCategoryResource::canCreate())
                ->label('استيراد Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->modalHeading('استيراد تصنيفات المنتجات من Excel')
                ->modalDescription('ارفع قالب .xlsx فقط. يمكن للتصنيف الأب أن يكون موجودًا مسبقًا أو ضمن الملف نفسه، وسيُفحص الملف كاملًا قبل الحفظ.')
                ->modalWidth('5xl')
                ->mountUsing(function (Schema $schema): void {
                    $this->productCategoryExcelImportReady = false;
                    $schema->fill();
                })
                ->modalSubmitAction(fn (Action $action): Action => $action
                    ->label('استيراد الملف')
                    ->disabled(fn (): bool => ! $this->productCategoryExcelImportReady))
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
                            $this->productCategoryExcelImportReady = false;

                            if (! $state instanceof TemporaryUploadedFile) {
                                return;
                            }

                            $analysis = app(ProductCategoryExcelImportService::class)->analyze(
                                $state->getRealPath(),
                                $state->getClientOriginalName(),
                            );

                            $this->productCategoryExcelImportReady = (bool) ($analysis['valid'] ?? false);
                        })
                        ->helperText('يسمح فقط بملفات .xlsx حتى 10 MB. استخدم parent_code بدل أي رقم ID.'),

                    View::make('filament.imports.product-category-excel-preview'),
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

                    $result = app(ProductCategoryExcelImportService::class)->import(
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
                        ->title('تم استيراد تصنيفات المنتجات بنجاح')
                        ->body('تمت إضافة '.number_format($result['imported_count']).' تصنيف من ملف Excel.')
                        ->send();
                }),
        ];
    }
}
