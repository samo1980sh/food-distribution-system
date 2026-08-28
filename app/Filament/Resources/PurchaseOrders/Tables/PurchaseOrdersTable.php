<?php

namespace App\Filament\Resources\PurchaseOrders\Tables;

use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Services\Purchasing\PurchaseOrderService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Throwable;

class PurchaseOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('purchase_order_number')
                    ->label('رقم الأمر')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('supplier.name')
                    ->label('المورد')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('warehouse.name')
                    ->label('مستودع الاستلام')
                    ->searchable(),
                TextColumn::make('order_date')
                    ->label('تاريخ الأمر')
                    ->date('Y-m-d')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::statusLabel($state))
                    ->color(fn (?string $state): string => self::statusColor($state)),
                TextColumn::make('total_amount')
                    ->label('الإجمالي')
                    ->money('SYP')
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('items_count')
                    ->counts('items')
                    ->label('الأصناف')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        PurchaseOrder::STATUS_DRAFT => 'مسودة',
                        PurchaseOrder::STATUS_APPROVED => 'معتمد',
                        PurchaseOrder::STATUS_PARTIALLY_RECEIVED => 'مستلم جزئيًا',
                        PurchaseOrder::STATUS_RECEIVED => 'مستلم بالكامل',
                        PurchaseOrder::STATUS_CANCELLED => 'ملغى',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->label('تعديل المسودة')
                        ->visible(fn (PurchaseOrder $record): bool => $record->isDraft())
                        ->slideOver(),

                    Action::make('approve')
                        ->label('اعتماد أمر الشراء')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (PurchaseOrder $record): bool => $record->isDraft()
                            && PurchaseOrderResource::canEdit($record))
                        ->requiresConfirmation()
                        ->action(function (PurchaseOrder $record, Action $action): void {
                            try {
                                app(PurchaseOrderService::class)->approve($record);
                            } catch (Throwable $exception) {
                                self::fail($action, 'تعذر اعتماد أمر الشراء', $exception);
                                return;
                            }

                            Notification::make()
                                ->success()
                                ->title('تم اعتماد أمر الشراء')
                                ->body('لم يتحرك المخزون بعد. يتم الترحيل فقط عند تسجيل الاستلام الفعلي.')
                                ->send();
                        }),

                    Action::make('receive')
                        ->label('تسجيل استلام')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('info')
                        ->visible(fn (PurchaseOrder $record): bool => $record->canReceive()
                            && PurchaseOrderResource::canView($record))
                        ->slideOver()
                        ->modalHeading(fn (PurchaseOrder $record): string => 'استلام '.$record->purchase_order_number)
                        ->fillForm(fn (PurchaseOrder $record): array => self::receiptDefaults($record))
                        ->schema([
                            DatePicker::make('receipt_date')
                                ->label('تاريخ الاستلام')
                                ->required()
                                ->native(false),
                            Textarea::make('notes')
                                ->label('مرجع / ملاحظات الاستلام')
                                ->rows(3),
                            Repeater::make('lines')
                                ->label('الأصناف المستلمة')
                                ->addable(false)
                                ->deletable(false)
                                ->reorderable(false)
                                ->columns(5)
                                ->schema([
                                    Hidden::make('purchase_order_item_id'),
                                    TextInput::make('product_name')
                                        ->label('المنتج')
                                        ->disabled()
                                        ->dehydrated(false)
                                        ->columnSpan(2),
                                    TextInput::make('remaining_quantity')
                                        ->label('المتبقي')
                                        ->disabled()
                                        ->dehydrated(false),
                                    TextInput::make('unit_label')
                                        ->label('وحدة التشغيل')
                                        ->disabled()
                                        ->dehydrated(false),
                                    TextInput::make('quantity')
                                        ->label('المستلم الآن')
                                        ->numeric()
                                        ->step('0.001')
                                        ->minValue(0)
                                        ->required(),
                                    TextInput::make('batch_number')
                                        ->label('التشغيلة'),
                                    DatePicker::make('expiry_date')
                                        ->label('الصلاحية')
                                        ->native(false),
                                ])
                                ->columnSpanFull(),
                        ])
                        ->action(function (PurchaseOrder $record, array $data, Action $action): void {
                            try {
                                $receipt = app(PurchaseOrderService::class)->receive(
                                    order: $record,
                                    lines: $data['lines'] ?? [],
                                    receiptDate: $data['receipt_date'] ?? null,
                                    notes: $data['notes'] ?? null,
                                );
                            } catch (Throwable $exception) {
                                self::fail($action, 'تعذر تسجيل الاستلام', $exception);
                                return;
                            }

                            Notification::make()
                                ->success()
                                ->title('تم ترحيل استلام المشتريات')
                                ->body('تم إنشاء سند '.$receipt->receipt_number.' وتحديث المخزون ومتوسط التكلفة.')
                                ->send();
                        }),

                    Action::make('cancel')
                        ->label('إلغاء أمر الشراء')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (PurchaseOrder $record): bool => in_array($record->status, [
                            PurchaseOrder::STATUS_DRAFT,
                            PurchaseOrder::STATUS_APPROVED,
                        ], true) && PurchaseOrderResource::canView($record))
                        ->requiresConfirmation()
                        ->slideOver()
                        ->schema([
                            Textarea::make('reason')
                                ->label('سبب الإلغاء')
                                ->required()
                                ->minLength(5)
                                ->rows(4),
                        ])
                        ->action(function (PurchaseOrder $record, array $data, Action $action): void {
                            try {
                                app(PurchaseOrderService::class)->cancel(
                                    $record,
                                    (string) ($data['reason'] ?? ''),
                                );
                            } catch (Throwable $exception) {
                                self::fail($action, 'تعذر إلغاء أمر الشراء', $exception);
                                return;
                            }

                            Notification::make()
                                ->success()
                                ->title('تم إلغاء أمر الشراء')
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('order_date', 'desc');
    }

    /** @return array<string, mixed> */
    private static function receiptDefaults(PurchaseOrder $record): array
    {
        $record->loadMissing('items.product.unit');

        return [
            'receipt_date' => now()->toDateString(),
            'notes' => null,
            'lines' => $record->items
                ->filter(fn ($item): bool => (float) $item->received_quantity < (float) $item->ordered_quantity)
                ->map(fn ($item): array => [
                    'purchase_order_item_id' => $item->id,
                    'product_name' => $item->product?->name_ar ?? '-',
                    'remaining_quantity' => round(
                        (float) $item->ordered_quantity - (float) $item->received_quantity,
                        3,
                    ),
                    'unit_label' => trim((string) (
                        $item->product?->unit?->symbol
                        ?: $item->product?->unit?->name_ar
                    )) ?: 'وحدة غير محددة',
                    'quantity' => 0,
                    'batch_number' => null,
                    'expiry_date' => null,
                ])
                ->values()
                ->all(),
        ];
    }

    private static function fail(Action $action, string $title, Throwable $exception): void
    {
        Notification::make()
            ->danger()
            ->title($title)
            ->body($exception->getMessage())
            ->persistent()
            ->send();

        $action->halt();
    }

    private static function statusLabel(?string $status): string
    {
        return match ($status) {
            PurchaseOrder::STATUS_DRAFT => 'مسودة',
            PurchaseOrder::STATUS_APPROVED => 'معتمد',
            PurchaseOrder::STATUS_PARTIALLY_RECEIVED => 'مستلم جزئيًا',
            PurchaseOrder::STATUS_RECEIVED => 'مستلم بالكامل',
            PurchaseOrder::STATUS_CANCELLED => 'ملغى',
            default => (string) $status,
        };
    }

    private static function statusColor(?string $status): string
    {
        return match ($status) {
            PurchaseOrder::STATUS_DRAFT => 'gray',
            PurchaseOrder::STATUS_APPROVED => 'info',
            PurchaseOrder::STATUS_PARTIALLY_RECEIVED => 'warning',
            PurchaseOrder::STATUS_RECEIVED => 'success',
            PurchaseOrder::STATUS_CANCELLED => 'danger',
            default => 'gray',
        };
    }
}
