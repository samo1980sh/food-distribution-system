<?php

namespace App\Support\Api;

use App\Http\Requests\Api\V1\Operational\CustomerPaymentWriteRequest;
use App\Http\Requests\Api\V1\Operational\DailyClosingWriteRequest;
use App\Http\Requests\Api\V1\Operational\SalesInvoiceWriteRequest;
use App\Http\Requests\Api\V1\Operational\SalesReturnWriteRequest;
use App\Http\Requests\Api\V1\Operational\VehicleExpenseWriteRequest;
use App\Http\Requests\Api\V1\Operational\VehicleLoadHandoverRequest;
use App\Models\CustomerPayment;
use App\Models\DailyClosing;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\VehicleExpense;
use App\Models\VehicleLoad;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

final class MobileSyncPushRegistry
{
    public const VERSION = 2;

    /**
     * @return array<string, array{
     *   model: class-string<Model>,
     *   request: class-string<FormRequest>,
     *   route_parameter: string,
     *   actions: list<string>
     * }>
     */
    public static function definitions(): array
    {
        return [
            'sales_invoices' => [
                'model' => SalesInvoice::class,
                'request' => SalesInvoiceWriteRequest::class,
                'route_parameter' => 'salesInvoice',
                'actions' => ['create', 'update', 'delete', 'confirm', 'cancel'],
            ],
            'customer_payments' => [
                'model' => CustomerPayment::class,
                'request' => CustomerPaymentWriteRequest::class,
                'route_parameter' => 'customerPayment',
                'actions' => ['create', 'update', 'delete', 'confirm', 'cancel'],
            ],
            'sales_returns' => [
                'model' => SalesReturn::class,
                'request' => SalesReturnWriteRequest::class,
                'route_parameter' => 'salesReturn',
                'actions' => ['create', 'update', 'delete', 'confirm', 'cancel'],
            ],
            'vehicle_loads' => [
                'model' => VehicleLoad::class,
                'request' => VehicleLoadHandoverRequest::class,
                'route_parameter' => 'vehicleLoad',
                'actions' => ['acknowledge'],
            ],
            'vehicle_expenses' => [
                'model' => VehicleExpense::class,
                'request' => VehicleExpenseWriteRequest::class,
                'route_parameter' => 'vehicleExpense',
                'actions' => ['create', 'update', 'delete', 'approve', 'reject'],
            ],
            'daily_closings' => [
                'model' => DailyClosing::class,
                'request' => DailyClosingWriteRequest::class,
                'route_parameter' => 'dailyClosing',
                'actions' => ['create', 'update', 'delete', 'refresh_totals', 'confirm', 'cancel'],
            ],
        ];
    }

    /** @return list<string> */
    public static function entities(): array
    {
        return array_keys(self::definitions());
    }

    /** @return list<string> */
    public static function actions(): array
    {
        return array_values(array_unique(array_merge(...array_values(array_map(
            static fn (array $definition): array => $definition['actions'],
            self::definitions(),
        )))));
    }

    /** @return array{model: class-string<Model>, request: class-string<FormRequest>, route_parameter: string, actions: list<string>} */
    public static function definition(string $entity): array
    {
        $definition = self::definitions()[$entity] ?? null;

        if ($definition === null) {
            throw new InvalidArgumentException("Unknown mobile sync push entity [{$entity}].");
        }

        return $definition;
    }

    public static function supports(string $entity, string $action): bool
    {
        $definition = self::definitions()[$entity] ?? null;

        return $definition !== null && in_array($action, $definition['actions'], true);
    }
}
