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
use App\Services\Inventory\InventoryAdjustmentService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

class ManageStockMovements extends ManageRecords
{
    protected static string $resource = StockMovementResource::class;

    public bool $openingInventoryExcelImportReady = false;

    /** @var array<string, mixed> */
    public array $openingInventoryExcelPreview = [];

    public function getHeading(): string
    {
        return 'سجل حركات المخزون';
    }

    public function getSubheading(): ?string
    {
        return 'سجل تدقيق لحركات المخزون. يتم الاستلام الاعتيادي من أوامر الشراء، وتحميل السيارة من حمولات السيارات، وتبقى التسويات للحالات الإدارية الموثقة فقط.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createInventoryAdjustment')
                ->label('تسوية مخزون')
                ->color('gray')
                ->visible(fn (): bool => Gate::allows('createAdjustment', StockMovement::class))
                ->modalHeading('تسوية مخزون')
                ->modalDescription('إجراء استثنائي موثق لزيادة أو إنقاص رصيد فعلي. لا يستخدم بدل الشراء أو التحويل.')
                ->slideOver()
                ->schema($this->adjustmentSchema())
                ->action(function (array $data, Action $action): void {
                    try {
                        Gate::authorize('createAdjustment', StockMovement::class);
                        $movement = app(InventoryAdjustmentService::class)->create(
                            warehouse: Warehouse::query()->findOrFail($data['warehouse_id']),
                            product: Product::query()->findOrFail($data['product_id']),
                            direction: (string) $data['direction'],
                            quantity: $data['quantity'],
                            reasonCategory: (string) $data['reason_category'],
                            reason: (string) $data['reason'],
                            unitCost: $data['unit_cost'] ?? null,
                            batchNumber: $data['batch_number'] ?? null,
                            expiryDate: $data['expiry_date'] ?? null,
                            movementDate: $data['movement_date'] ?? null,
                        );
                    } catch (Throwable $exception) {
                        Notification::make()->danger()->title('تعذر تسجيل التسوية')->body($exception->getMessage())->persistent()->send();
                        $action->halt();

                        return;
                    }

                    Notification::make()->success()->title('تم تسجيل تسوية المخزون')->body('رقم الحركة: '.$movement->movement_number)->send();
                }),

            Action::make('downloadOpeningInventoryExcelTemplate')
                ->visible(fn (): bool => Gate::allows('createAdjustment', StockMovement::class))
                ->label('تحميل قالب الرصيد الافتتاحي')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => app(OpeningInventoryExcelTemplateService::class)->download()),

            Action::make('importOpeningInventoryExcel')
                ->visible(fn (): bool => Gate::allows('createAdjustment', StockMovement::class))
                ->label('تهيئة الرصيد الافتتاحي')
                ->icon('heroicon-o-arrow-up-tray')
                ->modalHeading('تهيئة الرصيد الافتتاحي من Excel')
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
                    Gate::authorize('createAdjustment', StockMovement::class);
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
    private function adjustmentSchema(): array
    {
        return [
            Section::make('بيانات التسوية')->columns(2)->schema([
                DatePicker::make('movement_date')->label('تاريخ التسوية')->default(now())->required()->native(false),
                Select::make('warehouse_id')->label('المستودع')->options($this->fixedWarehouseOptions())->required()->searchable()->preload()->native(false),
                Select::make('product_id')->label('المنتج')->options($this->productOptions())->required()->searchable()->preload()->native(false),
                Select::make('direction')->label('اتجاه التسوية')->options([
                    InventoryAdjustmentService::DIRECTION_IN => 'زيادة الرصيد',
                    InventoryAdjustmentService::DIRECTION_OUT => 'إنقاص الرصيد',
                ])->required()->live()->native(false),
                TextInput::make('quantity')->label('الكمية')->numeric()->minValue(0.001)->step('0.001')->required(),
                TextInput::make('unit_cost')->label('تكلفة الوحدة')->numeric()->minValue(0.000001)
                    ->required(fn (Get $get): bool => $get('direction') === InventoryAdjustmentService::DIRECTION_IN)
                    ->visible(fn (Get $get): bool => $get('direction') === InventoryAdjustmentService::DIRECTION_IN)
                    ->helperText('تكلفة صريحة تدخل في المتوسط المرجح.'),
                TextInput::make('batch_number')->label('رقم التشغيلة')->maxLength(255),
                DatePicker::make('expiry_date')->label('تاريخ الصلاحية')->native(false),
                Select::make('reason_category')->label('تصنيف السبب')->options(function (Get $get): array {
                    $options = InventoryAdjustmentService::REASON_CATEGORIES;
                    $allowed = $get('direction') === InventoryAdjustmentService::DIRECTION_IN
                        ? ['inventory_variance', 'prior_entry_correction', 'discovered_surplus', 'other_exception']
                        : ['inventory_variance', 'damage', 'expired', 'loss', 'prior_entry_correction', 'other_exception'];

                    return array_intersect_key($options, array_flip($allowed));
                })->required()->native(false),
                Textarea::make('reason')->label('السبب التفصيلي')->required()->minLength(10)->maxLength(2000)->rows(4)->columnSpanFull(),
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
