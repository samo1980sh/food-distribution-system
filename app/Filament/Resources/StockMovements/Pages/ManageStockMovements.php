<?php

namespace App\Filament\Resources\StockMovements\Pages;

use App\Filament\Resources\StockMovements\StockMovementResource;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Authorization\AccessScopeService;
use App\Services\Imports\Excel\OpeningInventoryExcelImportService;
use App\Services\Imports\Excel\OpeningInventoryExcelTemplateService;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Inventory\WarehouseReplenishmentService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;
use Throwable;

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
        return 'التوريد والتحويل بين المستودعات لهما إجراءات مستقلة. مخزون السيارة يُغذّى فقط عبر حمولة السيارة المعتمدة، وتبقى التسويات اليدوية للحالات الإدارية الموثقة.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('receiveStock')
                ->label('توريد مخزون')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->visible(fn (): bool => StockMovementResource::canCreate())
                ->modalHeading('توريد / استلام مخزون')
                ->modalDescription('أدخل التوريد الفعلي للمستودع الرئيسي أو الفرعي. تكلفة الوحدة تحدّث متوسط التكلفة المتحرك تلقائيًا.')
                ->slideOver()
                ->schema($this->receiptSchema())
                ->action(function (array $data, Action $action): void {
                    try {
                        $movement = app(WarehouseReplenishmentService::class)->receive(
                            warehouse: Warehouse::query()->findOrFail($data['to_warehouse_id']),
                            product: Product::query()->findOrFail($data['product_id']),
                            quantity: $data['quantity'],
                            unitCost: $data['unit_cost'],
                            batchNumber: $data['batch_number'] ?? null,
                            expiryDate: $data['expiry_date'] ?? null,
                            notes: $data['notes'] ?? null,
                            movementDate: $data['movement_date'] ?? null,
                        );
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->danger()
                            ->title('تعذر توريد المخزون')
                            ->body($exception->getMessage())
                            ->persistent()
                            ->send();

                        $action->halt();

                        return;
                    }

                    Notification::make()
                        ->success()
                        ->title('تم توريد المخزون')
                        ->body('تم تسجيل الحركة '.$movement->movement_number.' وتحديث الرصيد ومتوسط التكلفة.')
                        ->send();
                }),

            Action::make('transferWarehouseStock')
                ->label('تحويل بين المستودعات')
                ->icon('heroicon-o-arrows-right-left')
                ->color('info')
                ->visible(fn (): bool => StockMovementResource::canCreate())
                ->modalHeading('تحويل مخزون بين المستودعات')
                ->modalDescription('للتحويل بين المستودعات الرئيسية والفرعية فقط. تحميل السيارة يتم من شاشة حمولات السيارات حتى يبقى مسار التسليم والتدقيق صحيحًا.')
                ->slideOver()
                ->schema($this->transferSchema())
                ->action(function (array $data, Action $action): void {
                    try {
                        $movement = app(WarehouseReplenishmentService::class)->transfer(
                            fromWarehouse: Warehouse::query()->findOrFail($data['from_warehouse_id']),
                            toWarehouse: Warehouse::query()->findOrFail($data['to_warehouse_id']),
                            product: Product::query()->findOrFail($data['product_id']),
                            quantity: $data['quantity'],
                            batchNumber: $data['batch_number'] ?? null,
                            expiryDate: $data['expiry_date'] ?? null,
                            notes: $data['notes'] ?? null,
                            movementDate: $data['movement_date'] ?? null,
                        );
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->danger()
                            ->title('تعذر تحويل المخزون')
                            ->body($exception->getMessage())
                            ->persistent()
                            ->send();

                        $action->halt();

                        return;
                    }

                    Notification::make()
                        ->success()
                        ->title('تم تحويل المخزون')
                        ->body('تم تسجيل الحركة '.$movement->movement_number.' وتحديث رصيدي المستودعين.')
                        ->send();
                }),

            CreateAction::make()
                ->label('تسوية مخزون إدارية')
                ->color('gray')
                ->visible(fn (): bool => StockMovementResource::canCreate())
                ->modalHeading('إضافة تسوية مخزون إدارية')
                ->modalDescription('للرصيد الافتتاحي أو الإخراج اليدوي الموثق فقط. استخدم إجراءات التوريد والتحويل المخصصة للحركات الاعتيادية.')
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

                            default => throw ValidationException::withMessages([
                                'movement_type' => 'نوع تسوية المخزون غير صالح.',
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

    /** @return array<int, mixed> */
    private function receiptSchema(): array
    {
        return [
            Section::make('بيانات التوريد')
                ->icon('heroicon-o-arrow-down-tray')
                ->columns(2)
                ->schema([
                    DatePicker::make('movement_date')
                        ->label('تاريخ التوريد')
                        ->default(now())
                        ->required()
                        ->native(false),
                    Select::make('to_warehouse_id')
                        ->label('المستودع المستلم')
                        ->options($this->fixedWarehouseOptions())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false),
                    Select::make('product_id')
                        ->label('المنتج')
                        ->options($this->productOptions())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false),
                    TextInput::make('quantity')
                        ->label('الكمية المستلمة')
                        ->numeric()
                        ->minValue(0.001)
                        ->step('0.001')
                        ->required(),
                    TextInput::make('unit_cost')
                        ->label('تكلفة الوحدة')
                        ->numeric()
                        ->minValue(0)
                        ->required()
                        ->helperText('تدخل في متوسط التكلفة المتحرك للمخزون.'),
                    TextInput::make('batch_number')
                        ->label('رقم التشغيلة')
                        ->maxLength(255),
                    DatePicker::make('expiry_date')
                        ->label('تاريخ الصلاحية')
                        ->native(false),
                    Textarea::make('notes')
                        ->label('مرجع / سبب التوريد')
                        ->required()
                        ->minLength(10)
                        ->maxLength(2000)
                        ->rows(4)
                        ->helperText('اكتب رقم فاتورة المورد أو مرجع الاستلام أو وصفًا واضحًا حتى إضافة دورة المشتريات الكاملة.')
                        ->columnSpanFull(),
                ]),
        ];
    }

    /** @return array<int, mixed> */
    private function transferSchema(): array
    {
        return [
            Section::make('بيانات التحويل')
                ->icon('heroicon-o-arrows-right-left')
                ->columns(2)
                ->schema([
                    DatePicker::make('movement_date')
                        ->label('تاريخ التحويل')
                        ->default(now())
                        ->required()
                        ->native(false),
                    Select::make('product_id')
                        ->label('المنتج')
                        ->options($this->productOptions())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false),
                    Select::make('from_warehouse_id')
                        ->label('من المستودع')
                        ->options($this->fixedWarehouseOptions())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false),
                    Select::make('to_warehouse_id')
                        ->label('إلى المستودع')
                        ->options($this->fixedWarehouseOptions())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->different('from_warehouse_id')
                        ->native(false),
                    TextInput::make('quantity')
                        ->label('الكمية المحولة')
                        ->numeric()
                        ->minValue(0.001)
                        ->step('0.001')
                        ->required(),
                    TextInput::make('batch_number')
                        ->label('رقم التشغيلة')
                        ->maxLength(255),
                    DatePicker::make('expiry_date')
                        ->label('تاريخ الصلاحية')
                        ->native(false),
                    Textarea::make('notes')
                        ->label('سبب التحويل')
                        ->required()
                        ->minLength(10)
                        ->maxLength(2000)
                        ->rows(4)
                        ->columnSpanFull(),
                ]),
        ];
    }

    /** @return array<int|string, string> */
    private function fixedWarehouseOptions(): array
    {
        $query = Warehouse::withoutGlobalScopes()
            ->where('status', 'active')
            ->whereIn('type', ['main', 'branch'])
            ->orderBy('name');

        $user = auth()->user();

        if ($user instanceof User) {
            $scope = app(AccessScopeService::class)->for($user);

            if (! $scope->unrestricted) {
                $query->whereIn('id', $scope->warehouseIds);
            }
        }

        return $query->pluck('name', 'id')->all();
    }

    /** @return array<int|string, string> */
    private function productOptions(): array
    {
        return Product::withoutGlobalScopes()
            ->where('status', 'active')
            ->orderBy('name_ar')
            ->pluck('name_ar', 'id')
            ->all();
    }
}
