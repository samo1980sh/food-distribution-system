<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\DistributionRoute;
use App\Models\Employee;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecureVehicleExpenseReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_receipt_endpoint_requires_bearer_authentication(): void
    {
        Storage::fake('local');

        $context = $this->context('UNAUTH');
        $expense = $this->expense($context, 'vehicle-expense-receipts/unauthorized.png');
        Storage::disk('local')->put($expense->receipt_path, 'private receipt');

        $this
            ->get(route('api.v1.operational.vehicle-expenses.receipt', [
                'vehicleExpense' => $expense,
            ]), ['Accept' => 'application/json'])
            ->assertUnauthorized();
    }

    public function test_mobile_receipt_upload_uses_private_storage_and_exposes_protected_api_url(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $context = $this->context('PRIVATE');
        $driver = $this->userForEmployee(User::ROLE_DRIVER, $context['driver']);
        $token = $this->tokenFor($driver);

        $response = $this
            ->withToken($token)
            ->post('/api/v1/operational/vehicle-expenses', [
                'client_reference' => 'secure-receipt-0001',
                'expense_date' => today()->toDateString(),
                'vehicle_id' => $context['vehicle']->id,
                'warehouse_id' => $context['warehouse']->id,
                'route_id' => $context['route']->id,
                'driver_id' => $context['driver']->id,
                'expense_type' => 'fuel',
                'amount' => 25,
                'payment_method' => 'cash',
                'receipt' => UploadedFile::fake()->image('receipt.png', 40, 40),
            ], [
                'Accept' => 'application/json',
            ])
            ->assertCreated();

        $expense = VehicleExpense::query()->findOrFail((int) $response->json('data.id'));
        $this->assertNotNull($expense->receipt_path);

        Storage::disk('local')->assertExists($expense->receipt_path);
        Storage::disk('public')->assertMissing($expense->receipt_path);

        $response->assertJsonPath(
            'data.receipt_url',
            route('api.v1.operational.vehicle-expenses.receipt', [
                'vehicleExpense' => $expense,
            ]),
        );

        $receiptResponse = $this
            ->withToken($token)
            ->get(route('api.v1.operational.vehicle-expenses.receipt', [
                'vehicleExpense' => $expense,
            ]), ['Accept' => 'image/png'])
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $cacheControl = (string) $receiptResponse->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
    }

    public function test_web_receipt_route_requires_login_and_record_authorization(): void
    {
        Storage::fake('local');

        $context = $this->context('WEB');
        $expense = $this->expense($context, 'vehicle-expense-receipts/web-receipt.png');
        Storage::disk('local')->put($expense->receipt_path, 'private receipt');

        $url = route('vehicle-expenses.receipt', ['vehicleExpense' => $expense]);

        $this
            ->get($url)
            ->assertRedirect(route('filament.admin.auth.login'));

        $admin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);

        $receiptResponse = $this
            ->actingAs($admin)
            ->get($url)
            ->assertOk();

        $cacheControl = (string) $receiptResponse->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
    }

    public function test_missing_private_receipt_returns_not_found_even_when_database_path_exists(): void
    {
        Storage::fake('local');

        $context = $this->context('MISSING');
        $expense = $this->expense($context, 'vehicle-expense-receipts/missing.png');

        $admin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this
            ->actingAs($admin)
            ->get(route('vehicle-expenses.receipt', ['vehicleExpense' => $expense]))
            ->assertNotFound();
    }

    public function test_filament_and_mobile_sources_do_not_publish_receipt_paths(): void
    {
        $form = file_get_contents(app_path('Filament/Resources/VehicleExpenses/Schemas/VehicleExpenseForm.php'));
        $infolist = file_get_contents(app_path('Filament/Resources/VehicleExpenses/Schemas/VehicleExpenseInfolist.php'));
        $resource = file_get_contents(app_path('Http/Resources/Api/V1/Operational/VehicleExpenseResource.php'));
        $writer = file_get_contents(app_path('Services/Api/MobileOperationalWriteService.php'));

        $this->assertStringContainsString("->visibility('private')", $form);
        $this->assertStringContainsString('->previewable(false)', $form);
        $this->assertStringContainsString('->preventFilePathTampering()', $form);
        $this->assertStringNotContainsString("->disk('public')", $form);

        $this->assertStringContainsString("'vehicle-expenses.receipt'", $infolist);
        $this->assertStringNotContainsString("asset('storage/'", $infolist);

        $this->assertStringContainsString(
            "'api.v1.operational.vehicle-expenses.receipt'",
            $resource,
        );
        $this->assertStringNotContainsString("Storage::disk('public')->url", $resource);

        $this->assertStringNotContainsString("store('vehicle-expense-receipts', 'public')", $writer);
        $this->assertStringNotContainsString("Storage::disk('public')->delete", $writer);
    }

    /** @return array<string, mixed> */
    private function context(string $suffix): array
    {
        $area = Area::query()->create([
            'code' => 'RECEIPT-AREA-'.$suffix,
            'name_ar' => 'منطقة '.$suffix,
            'status' => 'active',
        ]);

        $vehicle = Vehicle::query()->create([
            'code' => 'RECEIPT-VEH-'.$suffix,
            'plate_number' => 'RECEIPT-PLATE-'.$suffix,
            'status' => 'active',
        ]);

        $warehouse = Warehouse::query()->create([
            'vehicle_id' => $vehicle->id,
            'code' => 'RECEIPT-WH-'.$suffix,
            'name' => 'مستودع '.$suffix,
            'type' => 'vehicle',
            'status' => 'active',
        ]);

        $driver = Employee::query()->create([
            'employee_code' => 'RECEIPT-DRV-'.$suffix,
            'name' => 'سائق '.$suffix,
            'type' => 'driver',
            'status' => 'active',
        ]);

        $representative = Employee::query()->create([
            'employee_code' => 'RECEIPT-REP-'.$suffix,
            'name' => 'مندوب '.$suffix,
            'type' => 'sales_representative',
            'status' => 'active',
        ]);

        $route = DistributionRoute::query()->create([
            'area_id' => $area->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'sales_representative_id' => $representative->id,
            'code' => 'RECEIPT-ROUTE-'.$suffix,
            'name' => 'خط '.$suffix,
            'status' => 'active',
        ]);

        return compact('area', 'vehicle', 'warehouse', 'driver', 'representative', 'route');
    }

    /** @param array<string, mixed> $context */
    private function expense(array $context, string $receiptPath): VehicleExpense
    {
        return VehicleExpense::query()->create([
            'expense_number' => 'VEX-'.strtoupper(substr(sha1($receiptPath), 0, 12)),
            'expense_date' => today(),
            'vehicle_id' => $context['vehicle']->id,
            'warehouse_id' => $context['warehouse']->id,
            'route_id' => $context['route']->id,
            'driver_id' => $context['driver']->id,
            'expense_type' => 'fuel',
            'amount' => 10,
            'payment_method' => 'cash',
            'receipt_path' => $receiptPath,
            'status' => 'pending',
        ]);
    }

    private function userForEmployee(string $role, Employee $employee): User
    {
        $user = User::factory()->create(['role' => $role]);
        $employee->update(['user_id' => $user->id]);

        return $user;
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken(
            'secure-receipt-test',
            [(string) config('mobile_api.token_ability')],
        )->plainTextToken;
    }
}
