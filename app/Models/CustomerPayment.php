<?php

namespace App\Models;

use App\Services\Sales\CustomerPaymentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class CustomerPayment extends Model
{
    protected $fillable = [
        'payment_number',
        'customer_id',
        'sales_invoice_id',
        'sales_representative_id',
        'payment_date',
        'payment_method',
        'status',
        'amount',
        'reference_number',
        'notes',
        'created_by',
        'confirmed_by',
        'confirmed_at',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'confirmed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (CustomerPayment $payment): void {
            if (blank($payment->payment_number)) {
                $payment->payment_number = app(CustomerPaymentService::class)->generatePaymentNumber();
            }

            if (blank($payment->payment_date)) {
                $payment->payment_date = now()->toDateString();
            }

            if (blank($payment->status)) {
                $payment->status = 'draft';
            }

            if (blank($payment->created_by)) {
                $payment->created_by = Auth::id();
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }

    public function salesRepresentative(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'sales_representative_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }
}