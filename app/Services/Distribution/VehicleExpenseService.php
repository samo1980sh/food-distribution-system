<?php

namespace App\Services\Distribution;

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
            if (! $expense->isPending()) {
                throw new RuntimeException('لا يمكن اعتماد مصروف ليس بحالة قيد المراجعة.');
            }

            app(DailyClosingGuard::class)->ensureOpen($expense->expense_date, (int) $expense->warehouse_id);

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

    public function generateExpenseNumber(): string
    {
        return app(DocumentNumberService::class)->next('vehicle_expense', 'VEX');
    }
}