<?php

namespace App\Filament\Resources\StockMovements\Pages;

use App\Filament\Resources\StockMovements\StockMovementResource;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\Imports\Excel\OpeningInventoryExcelImportService;
use App\Services\Imports\Excel\OpeningInventoryExcelTemplateService;
use App\Services\Inventory\InventoryMovementService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;

class ManageStockMovements extends ManageRecords
{
    protected static string $resource = StockMovementResource::class;

    public bool $openingInventoryExcelImportReady = false;

    /** @var array<string, mixed> */
    public array $openingInventoryExcelPreview = [];

    public function getHeading(): string
    {
        return 'سجل حركات وتسويات المخزون';
    }

    public function getSubheading(): ?string
    {
        return 'الحركات الناتجة عن الاعتماد تُنشأ تلقائيًا. استخدم الحركة الإدارية فقط للرصيد الافتتاحي أو الإخراج اليدوي أو التحويل الموثق.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('حركة مخزون إدارية')
                ->visible(fn (): bool => StockMovementResource::canCreate())
                ->modalHeading('إضافة حركة مخزون إدارية')
                ->modalDescription('استخدمها فقط للرصيد الافتتاحي أو الإخراج اليدوي أو التحويل الإداري الموثق. الحركات التشغيلية تنشأ تلقائيًا من عمليات النظام.')
                ->slideOver()
                ->using(function (array $data): StockMovement {
                    $service = app(InventoryMovementService::class);
                    $product = Product::query()->findOrFail($data['product_id']);

                    try {
                        $movementDate = (string) $data['movement_date'];

                        return match ($data['movement_type']) {
                            'opening_balance' => $service->addStock(
                                warehouse: Warehouse::query()->findOrFail($data['to_warehouse_id']),
                                product: $product,
                                quantity: $data['quantity'],
                                batchNumber: $data['batch_number'] ?? null,
                                expiryDate: $data['expiry_date'] ?? null,
                                unitCost: $data['unit_cost'] ?? 0,
                                movementType: 'opening_balance',
                                notes: $data['notes'] ?? null,
                                movementDate: $movementDate,
                            ),

                            'manual_out' => $service->removeStock(
                                warehouse: Warehouse::query()->findOrFail($data['from_warehouse_id']),
                                product: $product,
                                quantity: $data['quantity'],
                                batchNumber: $data['batch_number'] ?? null,
                                expiryDate: $data['expiry_date'] ?? null,
                                movementType: 'manual_out',
                                notes: $data['notes'] ?? null,
                                movementDate: $movementDate,
                            ),

                            'warehouse_transfer' => $service->transfer(
                                fromWarehouse: Warehouse::query()->findOrFail($data['from_warehouse_id']),
                                toWarehouse: Warehouse::query()->findOrFail($data['to_warehouse_id']),
                                product: $product,
                                quantity: $data['quantity'],
                                batchNumber: $data['batch_number'] ?? null,
                                expiryDate: $data['expiry_date'] ?? null,
                                movementType: 'warehouse_transfer',
                                notes: $data['notes'] ?? null,
                                movementDate: $movementDate,
                            ),

                            default => throw ValidationException::withMessages([
                                'movement_type' => 'نوع حركة المخزون غير صالح.',
                            ]),
                        };
                    } catch (RuntimeException $exception) {
                        throw ValidationException::withMessages([
                            'quantity' => $exception->getMessage(),
                        ]);
                    }
                }),

            Action::make('downloadOpeningInventoryExcelTemplate')
                ->visible(fn (): bool => StockMovementResource::canCreate())
                ->label('تحميل قالب الرصيد الافتتاحي')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => app(OpeningInventoryExcelTemplateService::class)->download()),

            Action::make('importOpeningInventoryExcel')
                ->visible(fn (): bool => StockMovementResource::canCreate())
                ->label('استيراد رصيد افتتاحي')
                ->icon('heroicon-o-arrow-up-tray')
                ->modalHeading('استيراد الرصيد الافتتاحي من Excel')
                ->modalDescription('ارفع قالب .xlsx فقط. كل صف ينشئ حركة opening_balance عبر محرك المخزون الحالي، ويتم التراجع عن الملف كاملًا إذا وُجد أي خطأ.')
                ->modalWidth('7xl')
                ->mountUsing(function (Schema $schema): void {
                    $this->openingInventoryExcelImportReady = false;
                    $this->openingInventoryExcelPreview = [];
                    $schema->fill();
                })
                ->modalSubmitAction(fn (Action $action): Action => $action
                    ->label('استيراد الرصيد الافتتاحي')
                    ->disabled(fn (): bool => ! $this->openingInventoryExcelImportReady))
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
                            $this->openingInventoryExcelImportReady = false;
                            $this->openingInventoryExcelPreview = [];

                            if (! $state instanceof TemporaryUploadedFile) {
                                return;
                            }

                            $analysis = app(OpeningInventoryExcelImportService::class)->analyze(
                                $state->getRealPath(),
                                $state->getClientOriginalName(),
                            );

                            $this->openingInventoryExcelPreview = $analysis;
                            $this->openingInventoryExcelImportReady = (bool) ($analysis['valid'] ?? false);
                        })
                        ->helperText('يسمح فقط بملفات .xlsx حتى 10 MB. استخدم warehouse_code و sku من القوائم المرجعية داخل القالب.'),

                    View::make('filament.imports.opening-inventory-excel-preview'),
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

                    $result = app(OpeningInventoryExcelImportService::class)->import(
                        $file->getRealPath(),
                        $file->getClientOriginalName(),
                    );

                    if (! $result['valid']) {
                        Notification::make()
                            ->danger()
                            ->title('لم يتم استيراد أي رصيد')
                            ->body(implode("\n", array_slice($result['errors'], 0, 6)))
                            ->persistent()
                            ->send();

                        $action->halt();

                        return;
                    }

                    Notification::make()
                        ->success()
                        ->title('تم استيراد الرصيد الافتتاحي بنجاح')
                        ->body('تم إنشاء '.number_format($result['imported_count']).' حركة رصيد افتتاحي موثقة.')
                        ->send();
                }),
        ];
    }
}
