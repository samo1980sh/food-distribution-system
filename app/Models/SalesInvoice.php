<?php

namespace App\Models;

use App\Enums\OperationSource;
use App\Services\Operations\OperationalContextValidator;
use App\Services\Sales\CustomerFinancialService;
use App\Services\Sales\SalesInvoiceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class SalesInvoice extends Model
{
    protected $fillable = [
        'invoice_number',
        'customer_id',
        'vehicle_id',
        'route_id',
        'warehouse_id',
        'sales_representative_id',
        'invoice_date',
        'due_date',
        'status',
        'payment_type',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'paid_amount',
        'invoice_cash_amount',
        'remaining_amount',
        'credit_limit_snapshot',
        'credit_exposure_before',
        'credit_exposure_after',
        'credit_limit_override_requested',
        'credit_limit_overridden',
        'credit_limit_override_reason',
        'credit_limit_overridden_by',
        'credit_limit_overridden_at',
        'notes',
        'created_by',
        'client_reference',
        'client_payload_hash',
        'operation_source',
        'administrative_reason',
        'confirmed_by',
        'confirmed_at',
    ];

    protected $casts = [
        'operation_source' => OperationSource::class,
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'invoice_cash_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'credit_limit_snapshot' => 'decimal:2',
        'credit_exposure_before' => 'decimal:2',
        'credit_exposure_after' => 'decimal:2',
        'credit_limit_override_requested' => 'boolean',
        'credit_limit_overridden' => 'boolean',
        'credit_limit_overridden_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (SalesInvoice $record): void {
            app(OperationalContextValidator::class)->validateOperationalRecord($record);
            app(CustomerFinancialService::class)->normalizeInvoiceTerms($record);
        });

        static::creating(function (SalesInvoice $invoice): void {
            if (blank($invoice->operation_source)) {
                $invoice->operation_source = OperationSource::LEGACY;
            }

            if (blank($invoice->invoice_number)) {
                $invoice->invoice_number = app(SalesInvoiceService::class)->generateInvoiceNumber();
            }

            if (blank($invoice->invoice_date)) {
                $invoice->invoice_date = now()->toDateString();
            }

            if (blank($invoice->status)) {
                $invoice->status = 'draft';
            }

            if (blank($invoice->created_by)) {
                $invoice->created_by = Auth::id();
            }

            app(CustomerFinancialService::class)->normalizeInvoiceTerms($invoice);
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesInvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(DistributionRoute::class, 'route_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
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

    public function creditLimitOverrider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'credit_limit_overridden_by');
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
