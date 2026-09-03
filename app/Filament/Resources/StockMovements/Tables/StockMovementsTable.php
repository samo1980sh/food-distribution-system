<?php

namespace App\Filament\Resources\StockMovements\Tables;

use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use App\Filament\Resources\SalesReturns\SalesReturnResource;
use App\Filament\Resources\VehicleLoads\VehicleLoadResource;
use App\Models\Product;
use App\Models\PurchaseReceipt;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\VehicleLoad;
use App\Models\Warehouse;
use App\Services\Authorization\AccessScopeService;
use App\Services\Inventory\AdministrativeStockMovementService;
use App\Support\Formatting\QuantityFormatter;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Throwable;

class StockMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('movement_number')
                    ->label('رقم الحركة')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('movement_type')
                    ->label('نوع الحركة')
                    ->badge()
                    ->formatStateUsing(fn (?string $state, StockMovement $record): string => self::movementTypeLabel($state, $record))
                    ->color(fn (?string $state): string => self::movementTypeColor($state)),

                TextColumn::make('product.name_ar')
                    ->label('المنتج')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('fromWarehouse.name')
                    ->label('من')
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('toWarehouse.name')
                    ->label('إلى')
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('batch_number')
                    ->label('التشغيلة')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('expiry_date')
                    ->label('الصلاحية')
                    ->date('Y-m-d')
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('quantity')
                    ->label('الكمية')
                    ->state(fn (StockMovement $record): string => QuantityFormatter::formatWithUnit(
                        (float) $record->quantity,
                        $record->product?->unit,
                    ))
                    ->sortable(),

                TextColumn::make('unit_cost')
                    ->label('تكلفة الوحدة')
                    ->money('SYP')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('total_cost')
                    ->label('الإجمالي')
                    ->money('SYP')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('creator.name')
                    ->label('بواسطة')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('movement_date')
                    ->label('تاريخ الحركة')
                    ->date('Y-m-d')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('وقت التسجيل')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('movement_type')
                    ->label('نوع الحركة')
                    ->options([
                        'opening_balance' => 'رصيد افتتاحي',
                        'stock_receipt' => 'استلام مشتريات / توريد تاريخي',
                        'manual_out' => 'إخراج يدوي قديم',
                        'inventory_adjustment_in' => 'تسوية زيادة مخزون',
                        'inventory_adjustment_out' => 'تسوية نقص مخزون',
                        'warehouse_transfer' => 'تحويل بين المستودعات',
                        'administrative_reversal' => 'عكس حركة إدارية',
                        'vehicle_load_transfer' => 'تحميل سيارة',
                        'sales_invoice' => 'فاتورة بيع',
                        'sales_return' => 'مرتجع بيع',
                        'vehicle_load_cancellation' => 'إلغاء تحميل سيارة',
                        'sales_invoice_cancellation' => 'إلغاء فاتورة بيع',
                        'sales_return_cancellation' => 'إلغاء مرتجع بيع',
                    ]),

                SelectFilter::make('product_id')
                    ->label('المنتج')
                    ->relationship('product', 'name_ar')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('from_warehouse_id')
                    ->label('من المستودع')
                    ->relationship('fromWarehouse', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('to_warehouse_id')
                    ->label('إلى المستودع')
                    ->relationship('toWarehouse', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('viewMovementDetails')
                        ->label('عرض التفاصيل')
                        ->icon('heroicon-o-eye')
                        ->color('gray')
                        ->modalHeading(fn (StockMovement $record): string => 'تفاصيل الحركة '.$record->movement_number)
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('إغلاق')
                        ->slideOver()
                        ->schema(fn (StockMovement $record): array => self::detailsSchema($record)),

                    Action::make('openOriginalReference')
                        ->label('عرض العملية الأصلية')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->color('gray')
                        ->url(fn (StockMovement $record): string => self::originalReferenceUrl($record) ?? '#')
                        ->visible(fn (StockMovement $record): bool => self::originalReferenceUrl($record) !== null),

                    Action::make('correctAdministrativeMovement')
                        ->label('تصحيح الحركة')
                        ->icon('heroicon-o-pencil-square')
                        ->color('warning')
                        ->visible(fn (StockMovement $record): bool => self::canAdministrativelyActOn($record))
                        ->modalHeading(fn (StockMovement $record): string => 'تصحيح الحركة '.$record->movement_number)
                        ->modalDescription('سيعكس النظام الحركة الأصلية أولًا ثم ينشئ حركة مصححة جديدة. الحركة الأصلية تبقى محفوظة في سجل التدقيق ولا يتم تعديلها.')
                        ->slideOver()
                        ->fillForm(fn (StockMovement $record): array => self::correctionDefaults($record))
                        ->schema(fn (StockMovement $record): array => self::correctionSchema($record))
                        ->action(function (StockMovement $record, array $data, Action $action): void {
                            try {
                                Gate::authorize('createAdjustment', $record);
                                app(AdministrativeStockMovementService::class)->correct(
                                    movement: $record,
                                    corrected: $data,
                                    reason: (string) ($data['reason'] ?? ''),
                                );
                            } catch (Throwable $exception) {
                                Notification::make()
                                    ->danger()
                                    ->title('تعذر تصحيح حركة المخزون')
                                    ->body($exception->getMessage())
                                    ->persistent()
                                    ->send();

                                $action->halt();

                                return;
                            }

                            Notification::make()
                                ->success()
                                ->title('تم تصحيح الحركة')
                                ->body('تم إنشاء حركة عكس موثقة ثم حركة مصححة جديدة دون تعديل السجل الأصلي.')
                                ->send();
                        }),

                    Action::make('reverseAdministrativeMovement')
                        ->label('عكس الحركة')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('danger')
                        ->visible(fn (StockMovement $record): bool => self::canAdministrativelyActOn($record))
                        ->requiresConfirmation()
                        ->modalHeading(fn (StockMovement $record): string => 'عكس الحركة '.$record->movement_number)
                        ->modalDescription('لن يتم حذف الحركة الأصلية. سينشئ النظام حركة عكس جديدة تحفظ أثر التدقيق وتعيد تأثير المخزون بالاتجاه المعاكس.')
                        ->slideOver()
                        ->schema([
                            Section::make('توثيق حركة العكس')
                                ->icon('heroicon-o-arrow-uturn-left')
                                ->description('اكتب سببًا واضحًا ومؤرخًا. تبقى الحركة الأصلية محفوظة في سجل التدقيق.')
                                ->schema([
                                    DatePicker::make('movement_date')
                                        ->label('تاريخ حركة العكس')
                                        ->default(now())
                                        ->required()
                                        ->native(false),

                                    Textarea::make('reason')
                                        ->label('سبب عكس الحركة')
                                        ->required()
                                        ->minLength(10)
                                        ->maxLength(2000)
                                        ->rows(5),
                                ]),
                        ])
                        ->action(function (StockMovement $record, array $data, Action $action): void {
                            try {
                                Gate::authorize('createAdjustment', $record);
                                app(AdministrativeStockMovementService::class)->reverse(
                                    movement: $record,
                                    reason: (string) ($data['reason'] ?? ''),
                                    movementDate: $data['movement_date'] ?? null,
                                );
                            } catch (Throwable $exception) {
                                Notification::make()
                                    ->danger()
                                    ->title('تعذر عكس حركة المخزون')
                                    ->body($exception->getMessage())
                                    ->persistent()
                                    ->send();

                                $action->halt();

                                return;
                            }

                            Notification::make()
                                ->success()
                                ->title('تم عكس الحركة')
                                ->body('بقيت الحركة الأصلية محفوظة وتم إنشاء حركة عكس موثقة.')
                                ->send();
                        }),
                ])
                    ->label('الإجراءات')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->button(),
            ])
            ->toolbarActions([])
            ->defaultSort('movement_date', 'desc');
    }

    private static function canAdministrativelyActOn(StockMovement $record): bool
    {
        return Gate::allows('createAdjustment', $record)
            && app(AdministrativeStockMovementService::class)->canActOn($record);
    }

    /** @return array<string, mixed> */
    private static function correctionDefaults(StockMovement $record): array
    {
        return [
            'movement_date' => now()->toDateString(),
            'product_id' => $record->product_id,
            'from_warehouse_id' => $record->from_warehouse_id,
            'to_warehouse_id' => $record->to_warehouse_id,
            'batch_number' => $record->batch_number,
            'expiry_date' => $record->expiry_date?->toDateString(),
            'quantity' => $record->quantity,
            'unit_cost' => $record->unit_cost,
            'reason' => null,
        ];
    }

    /** @return array<int, mixed> */
    private static function detailsSchema(StockMovement $record): array
    {
        $record->loadMissing([
            'product',
            'fromWarehouse',
            'toWarehouse',
            'creator',
            'reference',
        ]);

        $isReversed = app(AdministrativeStockMovementService::class)->hasBeenReversed($record);

        return [
            Section::make('ملخص الحركة')
                ->icon('heroicon-o-document-text')
                ->columns(2)
                ->schema([
                    TextEntry::make('movement_number')
                        ->label('رقم الحركة')
                        ->state($record->movement_number)
                        ->copyable(),
                    TextEntry::make('movement_type_display')
                        ->label('نوع الحركة')
                        ->state(self::movementTypeLabel($record->movement_type, $record))
                        ->badge()
                        ->color(self::movementTypeColor($record->movement_type)),
                    TextEntry::make('audit_status')
                        ->label('حالة التدقيق')
                        ->state($isReversed ? 'تم عكس هذه الحركة' : 'الحركة أصلية وغير معكوسة')
                        ->badge()
                        ->color($isReversed ? 'warning' : 'success'),
                    TextEntry::make('movement_date_display')
                        ->label('تاريخ الحركة')
                        ->state($record->movement_date?->format('Y-m-d') ?? '-'),
                ]),

            Section::make('تفاصيل المخزون')
                ->icon('heroicon-o-cube')
                ->columns(2)
                ->schema([
                    TextEntry::make('product_display')
                        ->label('المنتج')
                        ->state($record->product?->name_ar ?? '-'),
                    TextEntry::make('from_warehouse_display')
                        ->label('من المستودع')
                        ->state($record->fromWarehouse?->name ?? '-'),
                    TextEntry::make('to_warehouse_display')
                        ->label('إلى المستودع')
                        ->state($record->toWarehouse?->name ?? '-'),
                    TextEntry::make('batch_display')
                        ->label('التشغيلة')
                        ->state($record->batch_number ?: '-'),
                    TextEntry::make('expiry_display')
                        ->label('تاريخ الصلاحية')
                        ->state($record->expiry_date?->format('Y-m-d') ?? '-'),
                    TextEntry::make('quantity_display')
                        ->label('الكمية')
                        ->state(QuantityFormatter::formatWithUnit(
                            (float) $record->quantity,
                            $record->product?->unit,
                        )),
                    TextEntry::make('unit_cost_display')
                        ->label('تكلفة الوحدة')
                        ->state((float) $record->unit_cost)
                        ->money('SYP'),
                    TextEntry::make('total_cost_display')
                        ->label('الإجمالي')
                        ->state((float) $record->total_cost)
                        ->money('SYP')
                        ->weight('bold'),
                ]),

            Section::make('التدقيق والمرجع')
                ->icon('heroicon-o-shield-check')
                ->columns(2)
                ->schema([
                    TextEntry::make('creator_display')
                        ->label('بواسطة')
                        ->state($record->creator?->name ?? '-'),
                    TextEntry::make('reference_display')
                        ->label('مرجع العملية')
                        ->state(self::referenceLabel($record)),
                    TextEntry::make('created_at_display')
                        ->label('وقت التسجيل')
                        ->state($record->created_at?->format('Y-m-d H:i') ?? '-'),
                    TextEntry::make('notes_display')
                        ->label('الملاحظات')
                        ->state($record->notes ?: 'لا توجد ملاحظات')
                        ->columnSpanFull(),
                ]),
        ];
    }

    /** @return array<int, mixed> */
    private static function correctionSchema(StockMovement $record): array
    {
        $fields = [
            DatePicker::make('movement_date')
                ->label('تاريخ التصحيح')
                ->default(now())
                ->required()
                ->native(false)
                ->helperText('يسجل العكس والحركة المصححة بهذا التاريخ.'),

            Select::make('product_id')
                ->label('المنتج الصحيح')
                ->options(self::productOptions())
                ->searchable()
                ->preload()
                ->required()
                ->native(false),
        ];

        if (in_array($record->movement_type, ['manual_out', 'inventory_adjustment_out', 'warehouse_transfer'], true)) {
            $fields[] = Select::make('from_warehouse_id')
                ->label('من المستودع')
                ->options(self::warehouseOptions($record->movement_type === 'warehouse_transfer'))
                ->searchable()
                ->preload()
                ->required()
                ->native(false);
        }

        if (in_array($record->movement_type, ['opening_balance', 'stock_receipt', 'inventory_adjustment_in', 'warehouse_transfer'], true)) {
            $fields[] = Select::make('to_warehouse_id')
                ->label('إلى المستودع')
                ->options(self::warehouseOptions(in_array($record->movement_type, ['stock_receipt', 'warehouse_transfer'], true)))
                ->searchable()
                ->preload()
                ->required()
                ->native(false);
        }

        $fields[] = TextInput::make('batch_number')
            ->label('رقم التشغيلة')
            ->maxLength(255);

        $fields[] = DatePicker::make('expiry_date')
            ->label('تاريخ الصلاحية')
            ->native(false);

        $fields[] = TextInput::make('quantity')
            ->label('الكمية الصحيحة')
            ->numeric()
            ->minValue(0.001)
            ->step('0.001')
            ->required();

        if (in_array($record->movement_type, ['opening_balance', 'stock_receipt', 'inventory_adjustment_in'], true)) {
            $fields[] = TextInput::make('unit_cost')
                ->label('تكلفة الوحدة الصحيحة')
                ->numeric()
                ->minValue(0)
                ->required();
        }

        return [
            Section::make('بيانات الحركة المصححة')
                ->icon('heroicon-o-pencil-square')
                ->description('سيعكس النظام الحركة الأصلية ثم يسجل هذه القيم كحركة جديدة موثقة.')
                ->columns(2)
                ->schema($fields),

            Section::make('توثيق التصحيح')
                ->icon('heroicon-o-document-text')
                ->schema([
                    Textarea::make('reason')
                        ->label('سبب التصحيح')
                        ->required()
                        ->minLength(10)
                        ->maxLength(2000)
                        ->rows(5),
                ]),
        ];
    }

    private static function referenceLabel(StockMovement $record): string
    {
        if ($record->reference_type === StockMovement::class && $record->reference instanceof StockMovement) {
            return 'حركة مخزون '.$record->reference->movement_number;
        }

        if (filled($record->reference_type) && filled($record->reference_id)) {
            return class_basename($record->reference_type).' #'.$record->reference_id;
        }

        return '-';
    }

    private static function movementTypeColor(?string $state): string
    {
        return match ($state) {
            'opening_balance' => 'success',
            'stock_receipt' => 'success',
            'manual_out' => 'danger',
            'inventory_adjustment_in' => 'success',
            'inventory_adjustment_out' => 'danger',
            'warehouse_transfer' => 'info',
            'administrative_reversal' => 'warning',
            'vehicle_load_transfer' => 'primary',
            'sales_invoice' => 'warning',
            'sales_return' => 'success',
            'vehicle_load_cancellation',
            'sales_invoice_cancellation',
            'sales_return_cancellation' => 'danger',
            default => 'gray',
        };
    }

    /** @return array<int|string, string> */
    private static function productOptions(): array
    {
        return Product::withoutGlobalScopes()
            ->where('status', 'active')
            ->orderBy('name_ar')
            ->pluck('name_ar', 'id')
            ->all();
    }

    /** @return array<int|string, string> */
    private static function warehouseOptions(bool $fixedOnly = false): array
    {
        $query = Warehouse::withoutGlobalScopes()
            ->where('status', 'active')
            ->when($fixedOnly, fn ($query) => $query->whereIn('type', ['main', 'branch']))
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

    private static function originalReferenceUrl(StockMovement $record): ?string
    {
        if (blank($record->reference_type) || blank($record->reference_id)) {
            return null;
        }

        if ($record->reference_type === StockMovement::class) {
            return null;
        }

        $resourceClass = match ($record->reference_type) {
            VehicleLoad::class => VehicleLoadResource::class,
            SalesInvoice::class => SalesInvoiceResource::class,
            SalesReturn::class => SalesReturnResource::class,
            default => null,
        };

        if ($resourceClass === null || ! class_exists($resourceClass)) {
            return null;
        }

        try {
            $reference = $record->reference()->first();

            if ($reference !== null && method_exists($resourceClass, 'canView') && ! $resourceClass::canView($reference)) {
                return null;
            }

            return $resourceClass::getUrl('view', ['record' => $record->reference_id]);
        } catch (Throwable) {
            return null;
        }
    }

    private static function movementTypeLabel(?string $state, ?StockMovement $movement = null): string
    {
        return match ($state) {
            'opening_balance' => 'رصيد افتتاحي',
            'stock_receipt' => $movement?->reference_type === PurchaseReceipt::class
                ? 'استلام مشتريات'
                : 'توريد مخزون تاريخي',
            'manual_out' => 'إخراج يدوي قديم',
            'inventory_adjustment_in' => 'تسوية زيادة مخزون',
            'inventory_adjustment_out' => 'تسوية نقص مخزون',
            'warehouse_transfer' => 'تحويل',
            'administrative_reversal' => 'عكس حركة إدارية',
            'vehicle_load_transfer' => 'تحميل سيارة',
            'sales_invoice' => 'فاتورة بيع',
            'sales_return' => 'مرتجع بيع',
            'vehicle_load_cancellation' => 'إلغاء تحميل سيارة',
            'sales_invoice_cancellation' => 'إلغاء فاتورة بيع',
            'sales_return_cancellation' => 'إلغاء مرتجع بيع',
            default => $state ?? '-',
        };
    }
}
