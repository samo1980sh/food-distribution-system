<?php

namespace Database\Seeders\Demo;

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryMovementService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class CleanOpeningInventorySeeder extends Seeder
{
    /** @var array<string, array{batch: string, expiry: ?string}> */
    private array $stockLots = [];

    public function run(): void
    {
        $admin = User::query()
            ->where('email', 'admin@demo.local')
            ->firstOrFail();

        Auth::login($admin);

        try {
            $this->stockLots = $this->buildStockLots();
            $this->seedOpeningInventory();
        } finally {
            Auth::logout();
        }
    }

    /** @return array<string, array{batch: string, expiry: ?string}> */
    private function buildStockLots(): array
    {
        return [
            'FD-001' => ['batch' => 'D-CLEAN-001', 'expiry' => today()->addDays(30)->toDateString()],
            'FD-002' => ['batch' => 'D-CLEAN-002', 'expiry' => today()->addDays(45)->toDateString()],
            'FD-003' => ['batch' => 'D-CLEAN-003', 'expiry' => today()->addDays(90)->toDateString()],
            'FD-004' => ['batch' => 'B-CLEAN-004', 'expiry' => today()->addDays(120)->toDateString()],
            'FD-005' => ['batch' => 'B-CLEAN-005', 'expiry' => today()->addDays(180)->toDateString()],
            'FD-006' => ['batch' => 'B-CLEAN-006', 'expiry' => today()->addDays(150)->toDateString()],
            'FD-007' => ['batch' => 'C-CLEAN-007', 'expiry' => today()->addDays(210)->toDateString()],
            'FD-008' => ['batch' => 'C-CLEAN-008', 'expiry' => today()->addDays(190)->toDateString()],
            'FD-009' => ['batch' => 'R-CLEAN-009', 'expiry' => today()->addDays(240)->toDateString()],
            'FD-010' => ['batch' => 'R-CLEAN-010', 'expiry' => today()->addDays(240)->toDateString()],
            'FD-011' => ['batch' => 'R-CLEAN-011', 'expiry' => today()->addDays(180)->toDateString()],
            'FD-012' => ['batch' => 'S-CLEAN-012', 'expiry' => today()->addDays(60)->toDateString()],
            'FD-013' => ['batch' => 'S-CLEAN-013', 'expiry' => today()->addDays(90)->toDateString()],
            'FD-014' => ['batch' => 'H-CLEAN-014', 'expiry' => null],
            'FD-015' => ['batch' => 'H-CLEAN-015', 'expiry' => null],
        ];
    }

    private function seedOpeningInventory(): void
    {
        $inventory = app(InventoryMovementService::class);
        $main = Warehouse::query()
            ->where('code', 'WH-MAIN')
            ->firstOrFail();

        $openingDate = today()->subDay();

        foreach (Product::query()->orderBy('id')->get() as $product) {
            $lot = $this->stockLots[$product->sku];

            $quantity = match ($product->sku) {
                'FD-004', 'FD-005', 'FD-006' => 420,
                'FD-013' => 260,
                default => 600,
            };

            $inventory->addStock(
                warehouse: $main,
                product: $product,
                quantity: $quantity,
                batchNumber: $lot['batch'],
                expiryDate: $lot['expiry'],
                unitCost: $product->purchase_price,
                movementType: 'opening_balance',
                notes: 'رصيد افتتاحي لبداية اختبار ميداني نظيف.',
                movementDate: $openingDate,
            );
        }
    }
}
