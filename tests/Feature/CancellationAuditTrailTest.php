<?php

namespace Tests\Feature;

use App\Http\Requests\Api\V1\Operational\CancelOperationalDocumentRequest;
use App\Http\Resources\Api\V1\Operational\CustomerPaymentResource;
use App\Http\Resources\Api\V1\Operational\DailyClosingResource;
use App\Http\Resources\Api\V1\Operational\SalesInvoiceResource;
use App\Http\Resources\Api\V1\Operational\SalesReturnResource;
use App\Http\Resources\Api\V1\Operational\VehicleLoadResource;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\DailyClosing;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleLoad;
use App\Models\Warehouse;
use App\Services\Distribution\DailyClosingService;
use App\Services\Distribution\VehicleLoadService;
use App\Services\Sales\CustomerPaymentService;
use App\Services\Sales\SalesInvoiceService;
use App\Services\Sales\SalesReturnService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Tests\TestCase;

class CancellationAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancellation_audit_columns_exist_on_all_supported_operational_documents(): void
    {
        foreach ([
            'sales_invoices',
            'sales_returns',
            'customer_payments',
            'vehicle_loads',
            'daily_closings',
        ] as $table) {
            $this->assertTrue(Schema::hasColumns($table, [
                'cancelled_by',
                'cancelled_at',
                'cancellation_reason',
            ]), "Missing cancellation audit columns on [{$table}].");
        }
    }

    public function test_official_cancellation_services_store_actor_time_and_reason(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_MANAGER,
        ]);
        $this->actingAs($user);

        $suffix = uniqid();
        $warehouse = Warehouse::query()->create([
            'code' => 'W-CANCEL-'.$suffix,
            'name' => 'Cancellation Warehouse '.$suffix,
            'type' => 'main',
            'status' => 'active',
        ]);

        $customer = Customer::query()->create([
            'code' => 'C-CANCEL-'.$suffix,
            'name' => 'Cancellation Customer '.$suffix,
            'customer_type' => 'grocery',
            'credit_limit' => 0,
            'credit_days' => 30,
            'payment_type' => 'cash',
            'status' => 'active',
        ]);

        $invoice = SalesInvoice::query()->create([
            'invoice_number' => 'INV-CANCEL-'.$suffix,
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'invoice_date' => now()->toDateString(),
            'status' => 'confirmed',
            'payment_type' => 'credit',
            'subtotal' => 100,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 100,
            'paid_amount' => 0,
            'invoice_cash_amount' => 0,
            'remaining_amount' => 100,
            'confirmed_by' => $user->id,
            'confirmed_at' => now(),
        ]);

        $salesReturn = SalesReturn::query()->create([
            'return_number' => 'RET-CANCEL-'.$suffix,
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'return_date' => now()->toDateString(),
            'status' => 'confirmed',
            'return_reason' => 'other',
            'subtotal' => 0,
            'discount_amount' => 0,
            'total_amount' => 0,
            'confirmed_by' => $user->id,
            'confirmed_at' => now(),
        ]);

        $payment = CustomerPayment::query()->create([
            'payment_number' => 'PAY-CANCEL-'.$suffix,
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'status' => 'confirmed',
            'amount' => 10,
            'confirmed_by' => $user->id,
            'confirmed_at' => now(),
        ]);

        $vehicle = Vehicle::query()->create([
            'code' => 'V-CANCEL-'.$suffix,
            'plate_number' => 'PLATE-'.$suffix,
            'status' => 'active',
        ]);

        $vehicleWarehouse = Warehouse::query()->create([
            'code' => 'W-CANCEL-VEH-'.$suffix,
            'name' => 'Cancellation Vehicle Warehouse '.$suffix,
            'type' => 'vehicle',
            'vehicle_id' => $vehicle->id,
            'status' => 'active',
        ]);

        $vehicleLoad = VehicleLoad::query()->create([
            'load_number' => 'VLD-CANCEL-'.$suffix,
            'vehicle_id' => $vehicle->id,
            'from_warehouse_id' => $warehouse->id,
            'to_warehouse_id' => $vehicleWarehouse->id,
            'load_date' => now()->toDateString(),
            'status' => 'approved',
            'total_quantity' => 0,
            'total_cost' => 0,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        $invoice = app(SalesInvoiceService::class)
            ->cancel($invoice, '  تصحيح فاتورة مدخلة بالخطأ.  ');
        $salesReturn = app(SalesReturnService::class)
            ->cancel($salesReturn, 'إلغاء المرتجع بعد مراجعة المستند.');
        $payment = app(CustomerPaymentService::class)
            ->cancel($payment, 'إلغاء التحصيل بسبب خطأ في المرجع.');
        $vehicleLoad = app(VehicleLoadService::class)
            ->cancel($vehicleLoad, 'إلغاء أمر التحميل لإعادة تجهيز الحمولة.');

        $closing = DailyClosing::query()->create([
            'closing_number' => 'DCL-CANCEL-'.$suffix,
            'closing_date' => now()->toDateString(),
            'warehouse_id' => $warehouse->id,
            'status' => 'confirmed',
            'confirmed_by' => $user->id,
            'confirmed_at' => now(),
        ]);
        $closing = app(DailyClosingService::class)
            ->cancel($closing, 'إلغاء الإغلاق لإجراء تصحيح معتمد.');

        $expected = [
            [$invoice, 'تصحيح فاتورة مدخلة بالخطأ.'],
            [$salesReturn, 'إلغاء المرتجع بعد مراجعة المستند.'],
            [$payment, 'إلغاء التحصيل بسبب خطأ في المرجع.'],
            [$vehicleLoad, 'إلغاء أمر التحميل لإعادة تجهيز الحمولة.'],
            [$closing, 'إلغاء الإغلاق لإجراء تصحيح معتمد.'],
        ];

        foreach ($expected as [$record, $reason]) {
            $this->assertCancellationAudit($record, $user, $reason);
        }

        $request = Request::create('/api/v1/audit-test', 'GET');
        $request->setUserResolver(fn (): User => $user);

        foreach ([
            [SalesInvoiceResource::class, $invoice],
            [SalesReturnResource::class, $salesReturn],
            [CustomerPaymentResource::class, $payment],
            [VehicleLoadResource::class, $vehicleLoad],
            [DailyClosingResource::class, $closing],
        ] as [$resourceClass, $record]) {
            $payload = $resourceClass::make($record)->resolve($request);

            $this->assertSame($record->cancellation_reason, $payload['cancellation_reason']);
            $this->assertSame($user->id, $payload['cancelled_by']);
            $this->assertNotNull($payload['cancelled_at']);
        }
    }

    public function test_blank_cancellation_reason_is_rejected_without_changing_document_state(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_MANAGER,
        ]);
        $this->actingAs($user);

        $suffix = uniqid();
        $warehouse = Warehouse::query()->create([
            'code' => 'W-CANCEL-REQ-'.$suffix,
            'name' => 'Cancellation Required '.$suffix,
            'type' => 'main',
            'status' => 'active',
        ]);

        $closing = DailyClosing::query()->create([
            'closing_number' => 'DCL-CANCEL-REQ-'.$suffix,
            'closing_date' => now()->toDateString(),
            'warehouse_id' => $warehouse->id,
            'status' => 'confirmed',
            'confirmed_by' => $user->id,
            'confirmed_at' => now(),
        ]);

        try {
            app(DailyClosingService::class)->cancel($closing, '   ');
            $this->fail('Blank cancellation reason must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertSame('يجب إدخال سبب الإلغاء.', $exception->getMessage());
        }

        $closing->refresh();

        $this->assertSame('confirmed', $closing->status);
        $this->assertNull($closing->cancelled_by);
        $this->assertNull($closing->cancelled_at);
        $this->assertNull($closing->cancellation_reason);
    }

    public function test_api_cancellation_request_requires_a_bounded_reason(): void
    {
        $request = CancelOperationalDocumentRequest::create('/', 'POST');

        $missing = Validator::make([], $request->rules());
        $tooLong = Validator::make([
            'reason' => str_repeat('x', 2001),
        ], $request->rules());
        $valid = Validator::make([
            'reason' => 'سبب واضح للإلغاء',
        ], $request->rules());

        $this->assertTrue($missing->fails());
        $this->assertTrue($tooLong->fails());
        $this->assertFalse($valid->fails());
    }

    public function test_filament_cancellation_actions_and_details_expose_required_audit_fields(): void
    {
        foreach ([
            'app/Filament/Resources/SalesInvoices/Actions/SalesInvoiceActions.php',
            'app/Filament/Resources/SalesReturns/Actions/SalesReturnActions.php',
            'app/Filament/Resources/CustomerPayments/Actions/CustomerPaymentActions.php',
            'app/Filament/Resources/VehicleLoads/Actions/VehicleLoadActions.php',
            'app/Filament/Resources/DailyClosings/Actions/DailyClosingActions.php',
        ] as $path) {
            $source = file_get_contents(base_path($path));

            $this->assertStringContainsString("Textarea::make('cancellation_reason')", $source);
            $this->assertStringContainsString('->required()', $source);
            $this->assertStringContainsString('->maxLength(2000)', $source);
        }

        foreach ([
            'app/Filament/Resources/SalesInvoices/Schemas/SalesInvoiceInfolist.php',
            'app/Filament/Resources/SalesReturns/Schemas/SalesReturnInfolist.php',
            'app/Filament/Resources/CustomerPayments/Schemas/CustomerPaymentInfolist.php',
            'app/Filament/Resources/VehicleLoads/Schemas/VehicleLoadInfolist.php',
            'app/Filament/Resources/DailyClosings/Schemas/DailyClosingInfolist.php',
        ] as $path) {
            $source = file_get_contents(base_path($path));

            $this->assertStringContainsString("TextEntry::make('canceller.name')", $source);
            $this->assertStringContainsString("TextEntry::make('cancelled_at')", $source);
            $this->assertStringContainsString("TextEntry::make('cancellation_reason')", $source);
        }
    }

    public function test_api_and_offline_sync_cancellation_paths_require_and_forward_reason(): void
    {
        foreach ([
            'app/Http/Controllers/Api/V1/Operational/SalesInvoiceController.php',
            'app/Http/Controllers/Api/V1/Operational/SalesReturnController.php',
            'app/Http/Controllers/Api/V1/Operational/CustomerPaymentController.php',
            'app/Http/Controllers/Api/V1/Operational/DailyClosingController.php',
        ] as $path) {
            $source = file_get_contents(base_path($path));

            $this->assertStringContainsString('CancelOperationalDocumentRequest $request', $source);
            $this->assertStringContainsString("\$request->validated('reason')", $source);
        }

        $sync = file_get_contents(app_path('Services/Api/MobileSyncPushOperationService.php'));

        $this->assertStringContainsString('CancelOperationalDocumentRequest::class', $sync);
        $this->assertStringContainsString("\$payload['reason']", $sync);
    }

    private function assertCancellationAudit(Model $record, User $user, string $reason): void
    {
        $record->refresh();

        $this->assertSame('cancelled', $record->status);
        $this->assertSame($reason, $record->cancellation_reason);
        $this->assertSame($user->id, (int) $record->cancelled_by);
        $this->assertNotNull($record->cancelled_at);
        $this->assertTrue($record->canceller->is($user));
    }
}
