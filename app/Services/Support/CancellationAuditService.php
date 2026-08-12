<?php

namespace App\Services\Support;

use Illuminate\Support\Facades\Auth;
use RuntimeException;

final class CancellationAuditService
{
    public const MAX_REASON_LENGTH = 2000;

    /**
     * @return array{
     *     cancellation_reason: string,
     *     cancelled_by: int|null,
     *     cancelled_at: \Illuminate\Support\Carbon
     * }
     */
    public function attributes(?string $reason): array
    {
        $reason = trim((string) $reason);

        if ($reason === '') {
            throw new RuntimeException('يجب إدخال سبب الإلغاء.');
        }

        if (mb_strlen($reason) > self::MAX_REASON_LENGTH) {
            throw new RuntimeException('سبب الإلغاء يجب ألا يتجاوز 2000 محرف.');
        }

        return [
            'cancellation_reason' => $reason,
            'cancelled_by' => Auth::id(),
            'cancelled_at' => now(),
        ];
    }
}
