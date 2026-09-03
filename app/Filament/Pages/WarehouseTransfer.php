<?php

namespace App\Filament\Pages;

use App\Enums\PermissionName;
use App\Filament\Clusters\InventoryCluster;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Authorization\AccessScopeService;
use App\Services\Inventory\WarehouseReplenishmentService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
use Throwable;

class WarehouseTransfer extends Page
{
    protected static ?string $cluster = InventoryCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationLabel = 'تحويل بين المستودعات';

    protected static ?string $title = 'تحويل بين المستودعات';

    protected static ?int $navigationSort = 50;

    protected string $view = 'filament.pages.warehouse-transfer';

    public static function canAccess(): bool
    {
        return auth()->user()?->can(PermissionName::INVENTORY_TRANSFERS_CREATE->value) === true;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('transferWarehouseStock')
                ->label('تسجيل تحويل')
                ->icon('heroicon-o-arrows-right-left')
                ->schema([
                    DatePicker::make('movement_date')->label('تاريخ التحويل')->default(now())->required()->native(false),
                    Select::make('product_id')->label('المنتج')->options($this->productOptions())->searchable()->preload()->required()->native(false),
                    Select::make('from_warehouse_id')->label('من المستودع')->options($this->warehouseOptions())->searchable()->preload()->required()->native(false),
                    Select::make('to_warehouse_id')->label('إلى المستودع')->options($this->warehouseOptions())->searchable()->preload()->required()->different('from_warehouse_id')->native(false),
                    TextInput::make('quantity')->label('الكمية')->numeric()->minValue(0.001)->step('0.001')->required(),
                    TextInput::make('batch_number')->label('رقم التشغيلة')->maxLength(255),
                    DatePicker::make('expiry_date')->label('تاريخ الصلاحية')->native(false),
                    Textarea::make('notes')->label('سبب التحويل')->required()->minLength(10)->maxLength(2000),
                ])
                ->action(function (array $data, Action $action): void {
                    try {
                        Gate::authorize('createTransfer', StockMovement::class);
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
                        Notification::make()->danger()->title('تعذر تحويل المخزون')->body($exception->getMessage())->persistent()->send();
                        $action->halt();

                        return;
                    }

                    Notification::make()->success()->title('تم تحويل المخزون')->body('رقم الحركة: '.$movement->movement_number)->send();
                }),
        ];
    }

    private function warehouseOptions(): array
    {
        $query = Warehouse::withoutGlobalScopes()->where('status', 'active')->whereIn('type', ['main', 'branch'])->orderBy('name');
        $user = auth()->user();

        if ($user instanceof User) {
            $scope = app(AccessScopeService::class)->for($user);
            if (! $scope->unrestricted) {
                $query->whereIn('id', $scope->warehouseIds);
            }
        }

        return $query->pluck('name', 'id')->all();
    }

    private function productOptions(): array
    {
        return Product::withoutGlobalScopes()->where('status', 'active')->orderBy('name_ar')->pluck('name_ar', 'id')->all();
    }
}
