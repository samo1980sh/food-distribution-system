<?php

namespace App\Services\Distribution;

use App\Exceptions\Api\OperationalApiException;
use App\Models\SalesJourney;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use App\Services\Support\DocumentNumberService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VehicleExpenseService
{
    public function approve(VehicleExpense $expense): VehicleExpense
    {
        return DB::transaction(function () use ($expense): VehicleExpense {
            $expense = VehicleExpense::query()
                ->lockForUpdate()
                ->findOrFail($expense->getKey());

            if (! $expense->isPending()) {
                throw new RuntimeException('لا يمكن اعتماد مصروف ليس بحالة قيد المراجعة.');
            }

            app(DailyClosingGuard::class)->ensureOpen($expense->expense_date, (int) $expense->warehouse_id);
            $this->applyApprovedOdometer($expense);

            $expense->forceFill([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
            ])->save();

            return $expense->refresh();
        });
    }

    public function reject(VehicleExpense $expense, ?string $reason = null): VehicleExpense
    {
        return DB::transaction(function () use ($expense, $reason): VehicleExpense {
            $expense = VehicleExpense::query()
                ->lockForUpdate()
                ->findOrFail($expense->getKey());

            if (! $expense->isPending()) {
                throw new RuntimeException('لا يمكن رفض مصروف ليس بحالة قيد المراجعة.');
            }

            $expense->forceFill([
                'status' => 'rejected',
                'rejected_by' => Auth::id(),
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ])->save();

            return $expense->refresh();
        });
    }

    private function applyApprovedOdometer(VehicleExpense $expense): void
    {
        if ($expense->odometer_reading === null) {
            return;
        }

        $reading = (int) $expense->odometer_reading;
        $journey = SalesJourney::withoutGlobalScopes()
            ->whereDate('journey_date', $expense->expense_date)
            ->where('vehicle_id', $expense->vehicle_id)
            ->when(
                $expense->route_id !== null,
                fn ($query) => $query->where('route_id', $expense->route_id),
            )
            ->when(
                $expense->sales_representative_id !== null,
                fn ($query) => $query->where('sales_representative_id', $expense->sales_representative_id),
            )
            ->whereIn('status', ['in_progress', 'completed'])
            ->latest('id')
            ->lockForUpdate()
            ->first();

        $vehicle = Vehicle::query()->lockForUpdate()->findOrFail((int) $expense->vehicle_id);
        $currentOdometer = $vehicle->current_odometer === null
            ? null
            : (int) $vehicle->current_odometer;

        if ($currentOdometer !== null && $reading < $currentOdometer) {
            throw new OperationalApiException(
                'قراءة عداد المصروف لا يمكن أن تكون أقل من آخر قراءة موثوقة للمركبة.',
                'vehicle_odometer_regression',
                422,
                ['odometer_reading' => ['يجب أن تكون قراءة العداد مساوية أو أكبر من آخر قراءة موثوقة.']],
            );
        }

        if ($journey?->start_odometer !== null && $reading < (int) $journey->start_odometer) {
            throw new OperationalApiException(
                'قراءة عداد المصروف تسبق قراءة بداية الرحلة.',
                'vehicle_expense_odometer_before_journey',
                422,
                ['odometer_reading' => ['يجب ألا تقل قراءة المصروف عن قراءة بداية الرحلة.']],
            );
        }

        if ($journey?->end_odometer !== null && $reading > (int) $journey->end_odometer) {
            throw new OperationalApiException(
                'قراءة عداد المصروف تتجاوز قراءة نهاية الرحلة المكتملة.',
                'vehicle_expense_odometer_after_journey',
                422,
                ['odometer_reading' => ['يجب ألا تتجاوز قراءة المصروف قراءة نهاية الرحلة المكتملة.']],
            );
        }

        if ($currentOdometer === null || $reading > $currentOdometer) {
            $vehicle->forceFill(['current_odometer' => $reading])->save();
        }
    }

    public function generateExpenseNumber(): string
    {
        return app(DocumentNumberService::class)->next('vehicle_expense', 'VEX');
    }
}