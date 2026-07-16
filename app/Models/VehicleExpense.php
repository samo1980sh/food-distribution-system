<?php

namespace App\Models;

use App\Services\Distribution\DailyClosingGuard;
use App\Services\Distribution\VehicleExpenseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class VehicleExpense extends Model
{
    protected $fillable = [
        'expense_number',
        'expense_date',
        'vehicle_id',
        'warehouse_id',
        'route_id',
        'driver_id',
        'sales_representative_id',
        'expense_type',
        'amount',
        'payment_method',
        'receipt_path',
        'status',
        'notes',
        'rejection_reason',
        'created_by',
        'client_reference',
        'client_payload_hash',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (VehicleExpense $expense): void {
            if (blank($expense->expense_number)) {
                $expense->expense_number = app(VehicleExpenseService::class)->generateExpenseNumber();
            }

            if (blank($expense->expense_date)) {
                $expense->expense_date = now()->toDateString();
            }

            if (blank($expense->status)) {
                $expense->status = 'pending';
            }

            if (blank($expense->payment_method)) {
                $expense->payment_method = 'cash';
            }

            if (blank($expense->created_by)) {
                $expense->created_by = Auth::id();
            }
        });

        static::saving(function (VehicleExpense $expense): void {
            if (
                in_array($expense->status, ['pending', 'approved'], true)
                && filled($expense->expense_date)
                && filled($expense->warehouse_id)
            ) {
                app(DailyClosingGuard::class)->ensureOpen($expense->expense_date, (int) $expense->warehouse_id);
            }
        });
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(DistributionRoute::class, 'route_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'driver_id');
    }

    public function salesRepresentative(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'sales_representative_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}