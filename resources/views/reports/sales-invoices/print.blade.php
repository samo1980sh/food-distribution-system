@php
    $statusLabels = [
        'draft' => 'مسودة',
        'confirmed' => 'معتمدة',
        'cancelled' => 'ملغاة',
    ];

    $paymentLabels = [
        'cash' => 'نقدي',
        'credit' => 'آجل',
        'partial' => 'دفعة جزئية',
    ];

    $money = fn ($value): string => number_format((float) $value, 2) . ' ل.س';
    $quantity = fn ($value): string => number_format((float) $value, 3);
@endphp

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>فاتورة البيع {{ $salesInvoice->invoice_number }}</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #eef2f4;
            color: #17252a;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 13px;
            line-height: 1.7;
        }

        .toolbar,
        .sheet {
            width: min(1040px, calc(100% - 32px));
            margin-right: auto;
            margin-left: auto;
        }

        .toolbar {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .toolbar button {
            border: 0;
            border-radius: 8px;
            padding: 10px 22px;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }

        .print-button {
            background: #0f766e;
            color: #fff;
        }

        .close-button {
            background: #dce4e7;
            color: #26363b;
        }

        .sheet {
            margin-top: 16px;
            margin-bottom: 40px;
            padding: 34px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 12px 35px rgba(15, 35, 43, 0.1);
        }

        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 24px;
            padding-bottom: 22px;
            border-bottom: 3px solid #0f766e;
        }

        .brand h1,
        .invoice-title h2 {
            margin: 0;
        }

        .brand h1 {
            color: #0f766e;
            font-size: 27px;
        }

        .brand p,
        .invoice-title p {
            margin: 3px 0 0;
            color: #607278;
        }

        .invoice-title {
            text-align: left;
        }

        .invoice-number {
            direction: ltr;
            display: inline-block;
        }

        .status {
            display: inline-flex;
            margin-top: 8px;
            padding: 3px 12px;
            border-radius: 999px;
            font-weight: 700;
        }

        .status-draft {
            background: #fef3c7;
            color: #92400e;
        }

        .status-confirmed {
            background: #dcfce7;
            color: #166534;
        }

        .status-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .section {
            margin-top: 25px;
        }

        .section-title {
            margin: 0 0 12px;
            padding-right: 10px;
            border-right: 4px solid #0f766e;
            font-size: 17px;
        }

        .meta-grid,
        .summary-grid {
            display: grid;
            gap: 11px;
        }

        .meta-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .summary-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .meta-item,
        .summary-card {
            padding: 10px 12px;
            border: 1px solid #dbe3e6;
            border-radius: 8px;
        }

        .meta-item span,
        .summary-card span {
            display: block;
            color: #64767c;
            font-size: 11px;
        }

        .meta-item strong,
        .summary-card strong {
            display: block;
            margin-top: 3px;
            font-size: 14px;
        }

        .summary-card strong {
            direction: ltr;
            text-align: right;
            font-size: 15px;
        }

        .summary-card.remaining {
            border-color: #f59e0b;
            background: #fffaf0;
        }

        .customer-address,
        .notes {
            min-height: 55px;
            padding: 12px;
            border: 1px solid #dbe3e6;
            border-radius: 8px;
            white-space: pre-wrap;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 8px 7px;
            border: 1px solid #cfdadd;
            vertical-align: middle;
        }

        th {
            background: #edf5f4;
            color: #24433f;
            font-weight: 700;
            white-space: nowrap;
        }

        td.number,
        th.number {
            direction: ltr;
            text-align: center;
            white-space: nowrap;
        }

        tbody tr:nth-child(even) {
            background: #fafcfc;
        }

        tfoot td {
            background: #edf5f4;
            font-weight: 700;
        }

        .signatures {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 28px;
            margin-top: 52px;
        }

        .signature {
            min-height: 80px;
            padding-top: 8px;
            border-top: 1px solid #64767c;
            text-align: center;
        }

        .invoice-footer {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-top: 28px;
            padding-top: 11px;
            border-top: 1px solid #dbe3e6;
            color: #718187;
            font-size: 10px;
        }

        @page {
            size: A4 portrait;
            margin: 9mm;
        }

        @media print {
            body {
                background: #fff;
                font-size: 9px;
            }

            .no-print {
                display: none !important;
            }

            .sheet {
                width: 100%;
                margin: 0;
                padding: 0;
                border-radius: 0;
                box-shadow: none;
            }

            .invoice-header {
                padding-bottom: 11px;
            }

            .section {
                margin-top: 12px;
            }

            .meta-grid {
                gap: 5px;
            }

            .summary-grid {
                gap: 5px;
            }

            .meta-item,
            .summary-card {
                padding: 5px 6px;
            }

            th,
            td {
                padding: 4px;
            }

            tr,
            .meta-item,
            .summary-card {
                break-inside: avoid;
            }

            .signatures {
                margin-top: 34px;
            }
        }

        @media (max-width: 800px) {
            .invoice-header {
                display: block;
            }

            .invoice-title {
                margin-top: 15px;
                text-align: right;
            }

            .meta-grid,
            .summary-grid,
            .signatures {
                grid-template-columns: 1fr;
            }

            .sheet {
                padding: 18px;
            }
        }
    </style>
</head>

<body>
    <div class="toolbar no-print">
        <button
            type="button"
            class="print-button"
            onclick="window.print()"
        >
            طباعة الفاتورة
        </button>

        <button
            type="button"
            class="close-button"
            onclick="window.close()"
        >
            إغلاق
        </button>
    </div>

    <main class="sheet">
        <header class="invoice-header">
            <div class="brand">
                <h1>FreshRoute</h1>
                <p>نظام إدارة توزيع المواد الغذائية والأسطول</p>
            </div>

            <div class="invoice-title">
                <h2>فاتورة بيع</h2>

                <p>
                    رقم الفاتورة:
                    <strong class="invoice-number">
                        {{ $salesInvoice->invoice_number }}
                    </strong>
                </p>

                <span class="status status-{{ $salesInvoice->status }}">
                    {{ $statusLabels[$salesInvoice->status] ?? $salesInvoice->status }}
                </span>
            </div>
        </header>

        <section class="section">
            <h3 class="section-title">بيانات الفاتورة والعميل</h3>

            <div class="meta-grid">
                <div class="meta-item">
                    <span>تاريخ الفاتورة</span>
                    <strong>{{ $salesInvoice->invoice_date?->format('Y-m-d') ?? '-' }}</strong>
                </div>

                <div class="meta-item">
                    <span>تاريخ الاستحقاق</span>
                    <strong>{{ $salesInvoice->due_date?->format('Y-m-d') ?? '-' }}</strong>
                </div>

                <div class="meta-item">
                    <span>العميل</span>
                    <strong>{{ $salesInvoice->customer?->name ?? '-' }}</strong>
                </div>

                <div class="meta-item">
                    <span>رمز العميل</span>
                    <strong>{{ $salesInvoice->customer?->code ?? '-' }}</strong>
                </div>

                <div class="meta-item">
                    <span>اسم المالك</span>
                    <strong>{{ $salesInvoice->customer?->owner_name ?? '-' }}</strong>
                </div>

                <div class="meta-item">
                    <span>الهاتف</span>
                    <strong dir="ltr">{{ $salesInvoice->customer?->phone ?? '-' }}</strong>
                </div>

                <div class="meta-item">
                    <span>الموبايل</span>
                    <strong dir="ltr">{{ $salesInvoice->customer?->mobile ?? '-' }}</strong>
                </div>

                <div class="meta-item">
                    <span>طريقة الدفع</span>
                    <strong>
                        {{ $paymentLabels[$salesInvoice->payment_type] ?? $salesInvoice->payment_type }}
                    </strong>
                </div>

                <div class="meta-item">
                    <span>مستودع البيع</span>
                    <strong>{{ $salesInvoice->warehouse?->name ?? '-' }}</strong>
                </div>

                <div class="meta-item">
                    <span>السيارة</span>
                    <strong dir="ltr">{{ $salesInvoice->vehicle?->plate_number ?? '-' }}</strong>
                </div>

                <div class="meta-item">
                    <span>خط التوزيع</span>
                    <strong>{{ $salesInvoice->route?->name ?? '-' }}</strong>
                </div>

                <div class="meta-item">
                    <span>مندوب المبيعات</span>
                    <strong>{{ $salesInvoice->salesRepresentative?->name ?? '-' }}</strong>
                </div>

                <div class="meta-item">
                    <span>تاريخ الاعتماد</span>
                    <strong>{{ $salesInvoice->confirmed_at?->format('Y-m-d H:i') ?? '-' }}</strong>
                </div>

                <div class="meta-item">
                    <span>الاستثناء الائتماني</span>
                    <strong>{{ $salesInvoice->credit_limit_overridden ? 'معتمد' : 'لا يوجد' }}</strong>
                </div>
            </div>

            @if (filled($salesInvoice->customer?->address))
                <div style="margin-top: 11px;">
                    <div class="customer-address">
                        <strong>العنوان:</strong>
                        {{ $salesInvoice->customer->address }}
                    </div>
                </div>
            @endif
        </section>

        <section class="section">
            <h3 class="section-title">مواد الفاتورة</h3>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>SKU</th>
                            <th>المنتج</th>
                            <th>التشغيلة</th>
                            <th>الصلاحية</th>
                            <th class="number">الكمية</th>
                            <th class="number">سعر الوحدة</th>
                            <th class="number">حسم المادة</th>
                            <th class="number">الإجمالي</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($salesInvoice->items as $item)
                            <tr>
                                <td class="number">{{ $loop->iteration }}</td>
                                <td class="number">{{ $item->product?->sku ?? '-' }}</td>
                                <td>{{ $item->product?->name_ar ?? '-' }}</td>
                                <td class="number">{{ $item->batch_number ?: '-' }}</td>
                                <td class="number">{{ $item->expiry_date?->format('Y-m-d') ?? '-' }}</td>
                                <td class="number">{{ $quantity($item->quantity) }}</td>
                                <td class="number">{{ $money($item->unit_price) }}</td>
                                <td class="number">{{ $money($item->discount_amount) }}</td>
                                <td class="number">{{ $money($item->line_total) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 25px;">
                                    لا توجد مواد ضمن هذه الفاتورة.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>

                    @if ($salesInvoice->items->isNotEmpty())
                        <tfoot>
                            <tr>
                                <td colspan="8">إجمالي مواد الفاتورة</td>
                                <td class="number">{{ $money($salesInvoice->subtotal) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </section>

        <section class="section">
            <h3 class="section-title">الملخص المالي</h3>

            <div class="summary-grid">
                <div class="summary-card">
                    <span>مجموع المواد</span>
                    <strong>{{ $money($salesInvoice->subtotal) }}</strong>
                </div>

                <div class="summary-card">
                    <span>حسم الفاتورة</span>
                    <strong>{{ $money($salesInvoice->discount_amount) }}</strong>
                </div>

                <div class="summary-card">
                    <span>الضريبة / الإضافات</span>
                    <strong>{{ $money($salesInvoice->tax_amount) }}</strong>
                </div>

                <div class="summary-card">
                    <span>إجمالي الفاتورة</span>
                    <strong>{{ $money($salesInvoice->total_amount) }}</strong>
                </div>

                <div class="summary-card">
                    <span>نقد الفاتورة عند الاعتماد</span>
                    <strong>{{ $money($salesInvoice->invoice_cash_amount) }}</strong>
                </div>

                <div class="summary-card">
                    <span>إجمالي المدفوع</span>
                    <strong>{{ $money($salesInvoice->paid_amount) }}</strong>
                </div>

                <div class="summary-card remaining">
                    <span>المبلغ المتبقي</span>
                    <strong>{{ $money($salesInvoice->remaining_amount) }}</strong>
                </div>
            </div>
        </section>

        @if ($salesInvoice->credit_limit_overridden)
            <section class="section">
                <h3 class="section-title">توثيق الاستثناء الائتماني</h3>

                <div class="customer-address">
                    <strong>الحد المسجل:</strong> {{ $money($salesInvoice->credit_limit_snapshot) }} —
                    <strong>التعرض بعد الفاتورة:</strong> {{ $money($salesInvoice->credit_exposure_after) }}
                    <br>
                    <strong>السبب:</strong> {{ $salesInvoice->credit_limit_override_reason }}
                </div>
            </section>
        @endif

        @if (filled($salesInvoice->notes))
            <section class="section">
                <h3 class="section-title">الملاحظات</h3>

                <div class="notes">{{ $salesInvoice->notes }}</div>
            </section>
        @endif

        <section class="signatures">
            <div class="signature">
                توقيع العميل
            </div>

            <div class="signature">
                توقيع مندوب المبيعات
            </div>

            <div class="signature">
                توقيع المحاسب
            </div>
        </section>

        <footer class="invoice-footer">
            <span>
                أُنشئت بواسطة:
                {{ $salesInvoice->creator?->name ?? '-' }}
            </span>

            <span>
                اعتُمدت بواسطة:
                {{ $salesInvoice->confirmer?->name ?? '-' }}
            </span>

            <span>
                تاريخ الطباعة:
                {{ now()->format('Y-m-d H:i') }}
            </span>
        </footer>
    </main>
</body>
</html>