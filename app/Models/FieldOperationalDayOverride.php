<?php

namespace App\Models;

use App\Services\Distribution\FieldRouteAssignmentResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class FieldOperationalDayOverride extends Model
{
    protected $fillable = [
        'operation_date',
        'route_id',
        'vehicle_id',
        'sales_representative_id',
        'status',
        'reason',
        'created_by',
        'cancelled_by',
        'cancelled_at',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    protected $casts = [
        'operation_date' => 'date',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $override): void {
            $override->assertKnownStatus();
            $override->created_by ??= Auth::id();

            if ($override->status === 'active') {
                $override->prepareOperationalContextForActivation();
                $override->cancelled_by = null;
                $override->cancelled_at = null;
            } else {
                $override->cancelled_by ??= Auth::id();
                $override->cancelled_at ??= now();
            }
        });

        static::updating(function (self $override): void {
            $override->assertKnownStatus();

            if ($override->status === 'cancelled') {
                if ($override->isDirty('status')) {
                    $override->cancelled_by ??= Auth::id();
                    $override->cancelled_at ??= now();
                }

                return;
            }

            if (
                $override->isDirty('status')
                || $override->isDirty('operation_date')
                || $override->isDirty('route_id')
                || $override->isDirty('vehicle_id')
                || $override->isDirty('sales_representative_id')
            ) {
                $override->prepareOperationalContextForActivation();
            }

            $override->cancelled_by = null;
            $override->cancelled_at = null;
        });
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(DistributionRoute::class, 'route_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function salesRepresentative(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'sales_representative_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    private function assertKnownStatus(): void
    {
        if (! in_array($this->status, ['active', 'cancelled'], true)) {
            throw new RuntimeException('حالة تصريح التشغيل الاستثنائي غير صالحة.');
        }
    }

    private function prepareOperationalContextForActivation(): void
    {
        $route = DistributionRoute::withoutGlobalScopes()
            ->with(['vehicle', 'salesRepresentative'])
            ->find($this->route_id);

        if (
            $route === null
            || $route->status !== 'active'
            || $route->vehicle_id === null
            || $route->sales_representative_id === null
        ) {
            throw new RuntimeException('يتطلب التشغيل الاستثنائي مسارًا فعالًا بمركبة ومندوب مبيعات محددين.');
        }

        if (app(FieldRouteAssignmentResolver::class)->isScheduledFor($route, $this->operation_date)) {
            throw new RuntimeException('لا يلزم تصريح استثنائي لأن التاريخ ضمن جدول المسار العادي.');
        }

        $this->vehicle_id = $route->vehicle_id;
        $this->sales_representative_id = $route->sales_representative_id;
    }
}
