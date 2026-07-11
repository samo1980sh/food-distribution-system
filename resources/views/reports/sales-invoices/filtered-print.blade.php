@php
    $money = fn ($value): string => number_format((float) $value, 2) . ' ل.س';

    $statusClasses = [
        'draft' => 'status-draft',
        'confirmed' => 'status-confirmed',
        'cancelled' => 'status-cancelled',
    ];
@endphp

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>طباعة نتائج تقرير المبيعات</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #eef2f4;
            color: #17252a;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
            line-height: 1.6;
        }

        .toolbar,
        .sheet {
            width: min(1500px, calc(100% - 30px));
            margin-right: auto;
            margin-left: auto;
        }

        .toolbar {
            display: flex;
            gap: 10px;
            margin-top: 18px;
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
            margin-top: 14px;
            margin-bottom: 35px;
            padding: 28px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 12px 35px rgba(15, 35, 43, 0.1);
        }

        .report-header {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            padding-bottom: 18px;
            border-bottom: 3px solid #0f766e;
        }

        .brand h1,
        .report-title h2 {
            margin: 0;
        }

        .brand h1 {
            color: #0f766e;
            font-size: 25px;
        }

        .brand p,
        .report-title p {
            margin: 3px 0 0;
            color: #607278;
        }

        .report-title {
            text-align: left;
        }

        .section {
            margin-top: 22px;
        }

        .section-title {
            margin: 0 0 11px;
            padding-right: 9px;
            border-right: 4px solid #0f766e;
            font-size: 16px;
        }

        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .filter {
            padding: 6px 11px;
            border: 1px solid #cfdadd;
            border-radius: 999px;
            background: #f8fafb;
        }

        .filter strong {
            color: #0f766e;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
        }

        .summary-card {
            padding: 10px 12px;
            border: 1px solid #dbe3e6;
            border-radius: 8px;
        }

        .summary-card span {
            display: block;
            color: #64767c;
            font-size: 11px;
        }

        .summary-card strong {
            display: block;
            margin-top: 3px;
            direction: ltr;
            text-align: right;
            font-size: 14px;
        }

        .summary-card.remaining {
            border-color: #f59e0b;
            background: #fffaf0;
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
            padding: 7px 6px;
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

        .status {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-weight: 700;
            white-space: nowrap;
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

        tfoot td {
            background: #edf5f4;
            font-weight: 700;
        }

        .empty {
            padding: 30px;
            text-align: center;
            color: #64767c;
        }

        .report-footer {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-top: 25px;
            padding-top: 11px;
            border-top: 1px solid #dbe3e6;
            color: #718187;
            font-size: 10px;
        }

        @page {
            size: A4 landscape;
            margin: 8mm;
        }

        @media print {
            body {
                background: #fff;
                font-size: 8px;
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

            .report-header {
                padding-bottom: 9px;
            }

            .section {
                margin-top: 10px;
            }

            .summary-grid {
                gap: 5px;
            }

            .summary-card {
                padding: 5px 6px;
            }

            .summary-card span {
                font-size: 7.5px;
            }

            .summary-card strong {
                font-size: 9px;
            }

            th,
            td {
                padding: 3px;
            }

            tr,
            .summary-card,
            .filter {
                break-inside: avoid;
            }

            .report-footer {
                margin-top: 12px;
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
            طباعة النتائج
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
        <header class="report-header">
            <div class="brand">
                <h1>FreshRoute</h1>
                <p>نظام إدارة توزيع المواد الغذائية والأسطول</p>
            </div>

            <div class="report-title">
                <h2>نتائج تقرير المبيعات</h2>
                <p>عدد الفواتير: {{ number_format($totals['count']) }}</p>
            </div>
        </header>

        <section class="section">
            <h3 class="section-title">الفلاتر المطبقة</h3>

            <div class="filters">
                @forelse ($filterSummary as $label => $value)
                    <span class="filter">
                        <strong>{{ $label }}:</strong>
                        {{ $value }}
                    </span>
                @empty
                    <span class="filter">جميع فواتير البيع</span>
                @endforelse
            </div>
        </section>

        <section class="section">
            <h3 class="section-title">الإجماليات</h3>

            <div class="summary-grid">
                <div class="summary-card">
                    <span>عدد الفواتير</span>
                    <strong>{{ number_format($totals['count']) }}</strong>
                </div>

                <div class="summary-card">
                    <span>مجموع المواد</span>
                    <strong>{{ $money($totals['subtotal']) }}</strong>
                </div>

                <div class="summary-card">
                    <span>إجمالي الحسومات</span>
                    <strong>{{ $money($totals['discount_amount']) }}</strong>
                </div>

                <div class="summary-card">
                    <span>الضريبة / الإضافات</span>
                    <strong>{{ $money($totals['tax_amount']) }}</strong>
                </div>

                <div class="summary-card">
                    <span>إجمالي المبيعات</span>
                    <strong>{{ $money($totals['total_amount']) }}</strong>
                </div>

                <div class="summary-card">
                    <span>نقد الفواتير</span>
                    <strong>{{ $money($totals['invoice_cash_amount']) }}</strong>
                </div>

                <div class="summary-card">
                    <span>إجمالي المدفوع</span>
                    <strong>{{ $money($totals['paid_amount']) }}</strong>
                </div>

                <div class="summary-card remaining">
                    <span>إجمالي المتبقي</span>
                    <strong>{{ $money($totals['remaining_amount']) }}</strong>
                </div>
            </div>
        </section>

        <section class="section">
            <h3 class="section-title">تفاصيل الفواتير</h3>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>التاريخ</th>
                            <th>رقم الفاتورة</th>
                            <th>العميل</th>
                            <th>المستودع</th>
                            <th>السيارة</th>
                            <th>المندوب</th>
                            <th>الدفع</th>
                            <th>الحالة</th>
                            <th class="number">المجموع</th>
                            <th class="number">الحسم</th>
                            <th class="number">الضريبة</th>
                            <th class="number">الإجمالي</th>
                            <th class="number">المدفوع</th>
                            <th class="number">المتبقي</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($invoices as $invoice)
                            <tr>
                                <td class="number">{{ $loop->iteration }}</td>
                                <td class="number">{{ $invoice->invoice_date?->format('Y-m-d') ?? '-' }}</td>
                                <td class="number">{{ $invoice->invoice_number }}</td>
                                <td>{{ $invoice->customer?->name ?? '-' }}</td>
                                <td>{{ $invoice->warehouse?->name ?? '-' }}</td>
                                <td class="number">{{ $invoice->vehicle?->plate_number ?? '-' }}</td>
                                <td>{{ $invoice->salesRepresentative?->name ?? '-' }}</td>
                                <td>{{ $paymentLabels[$invoice->payment_type] ?? $invoice->payment_type }}</td>

                                <td>
                                    <span class="status {{ $statusClasses[$invoice->status] ?? '' }}">
                                        {{ $statusLabels[$invoice->status] ?? $invoice->status }}
                                    </span>
                                </td>

                                <td class="number">{{ $money($invoice->subtotal) }}</td>
                                <td class="number">{{ $money($invoice->discount_amount) }}</td>
                                <td class="number">{{ $money($invoice->tax_amount) }}</td>
                                <td class="number">{{ $money($invoice->total_amount) }}</td>
                                <td class="number">{{ $money($invoice->paid_amount) }}</td>
                                <td class="number">{{ $money($invoice->remaining_amount) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="15" class="empty">
                                    لا توجد فواتير مطابقة للفلاتر الحالية.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>

                    @if ($invoices->isNotEmpty())
                        <tfoot>
                            <tr>
                                <td colspan="9">الإجمالي</td>
                                <td class="number">{{ $money($totals['subtotal']) }}</td>
                                <td class="number">{{ $money($totals['discount_amount']) }}</td>
                                <td class="number">{{ $money($totals['tax_amount']) }}</td>
                                <td class="number">{{ $money($totals['total_amount']) }}</td>
                                <td class="number">{{ $money($totals['paid_amount']) }}</td>
                                <td class="number">{{ $money($totals['remaining_amount']) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </section>

        <footer class="report-footer">
            <span>
                أُنشئ بواسطة:
                {{ $generatedBy ?? '-' }}
            </span>

            <span>
                تاريخ الإنشاء:
                {{ now()->format('Y-m-d H:i') }}
            </span>

            <span>FreshRoute — تقرير المبيعات</span>
        </footer>
    </main>
</body>
</html>