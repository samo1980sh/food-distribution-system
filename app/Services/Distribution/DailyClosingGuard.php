<?php

namespace App\Services\Distribution;

use App\Models\DailyClosing;
use Illuminate\Support\Carbon;
use RuntimeException;

class DailyClosingGuard
{
    public function ensureOpen(Carbon|string $date, int $warehouseId): void
    {
        $isClosed = DailyClosing::query()
            ->where('status', 'confirmed')
            ->whereDate('closing_date', $date)
            ->where('warehouse_id', $warehouseId)
            ->exists();

        if ($isClosed) {
            throw new RuntimeException('لا يمكن تنفيذ العملية لأن هذا التاريخ والمستودع لديهما إغلاق يومي معتمد. قم بإلغاء الإغلاق أولاً ثم أعد تنفيذ العملية.');
        }
    }
}
