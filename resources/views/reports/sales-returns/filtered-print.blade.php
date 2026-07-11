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

    <title>طباعة نتائج تقرير مرتجعات البيع</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #eef2f4;
            color: #17252a;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 11px;
            line-height: 1.5;
        }

        .toolbar,
        .sheet {
            width: min(1550px, calc(100% - 30px));
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
            padding: 26px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 12px 35px rgba(15, 35, 43, 0.1);
        }

        .report-header {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            padding-bottom: 17px;
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
            margin-top: 20px;
        }

        .section-title {
            margin: 0 0 10px;
            padding-right: 9px;
            border-right: 4px solid #0f766e;
            font-size: 15px;
        }

        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
        }

        .filter {
            padding: 5px 10px;
            border: 1px solid #cfdadd;
            border-radius: 999px;
            background: #f8fafb;
        }

        .filter strong {
            color: #0f766e;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 9px;
        }

        .summary-card {
            padding: 9px 11px;
            border: 1px solid #dbe3e6;
            border-radius: 8px;
        }

        .summary-card span {
            display: block;
            color: #64767c;
            font-size: 10px;
        }

        .summary-card strong {
            display: block;
            margin-top: 3px;
            direction: ltr;
            text-align: right;
            font-size: 13px;
        }

        .summary-card.total {
            border-color: #0f766e;
            background: #f3faf9;
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
            padding: 6px 5px;
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
            padding: 2px 7px;
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
            margin-top: 24px;
            padding-top: 10px;
            border-top: 1px solid #dbe3e6;
            color: #718187;
            font-size: 10px;
        }

        @page {
            size: A4 landscape;
            margin: 7mm;
        }

        @media print {
            body {
                background: #fff;
                font-size: 7px;
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
                padding-bottom: 8px;
            }

            .section {
                margin-top: 8px;
            }

            .summary-grid {
                gap: 4px;
            }

            .summary-card {
                padding: 4px 5px;
            }

            .summary-card span {
                font-size: 6.5px;
            }

            .summary-card strong {
                font-size: 8px;
            }

            th,
            td {
                padding: 2.5px;
            }

            tr,
            .summary-card,
            .filter {
                break-inside: avoid;
            }

            .report-footer {
                margin-top: 9px;
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
                <h2>نتائج تقرير مرتجعات البيع</h2>
                <p>
                    عدد المرتجعات:
                    {{ number_format($totals['count']) }}
                </p>
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
                    <span class="filter">جميع مرتجعات البيع</span>
                @endforelse
            </div>
        </section>

        <section class="section">
            <h3 class="section-title">الإجماليات</h3>

            <div class="summary-grid">
                <div class="summary-card">
                    <span>عدد المرتجعات</span>
                    <strong>{{ number_format($totals['count']) }}</strong>
                </div>

                <div class="summary-card">
                    <span>إجمالي عدد المواد</span>
                    <strong>{{ number_format($totals['items_count']) }}</strong>
                </div>

                <div class="summary-card">
                    <span>مجموع المواد</span>
                    <strong>{{ $money($totals['subtotal']) }}</strong>
                </div>

                <div class="summary-card">
                    <span>إجمالي الحسومات</span>
                    <strong>{{ $money($totals['discount_amount']) }}</strong>
                </div>

                <div class="summary-card total">
                    <span>صافي المرتجعات</span>
                    <strong>{{ $money($totals['total_amount']) }}</strong>
                </div>
            </div>
        </section>

        <section class="section">
            <h3 class="section-title">تفاصيل المرتجعات</h3>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>التاريخ</th>
                            <th>رقم المرتجع</th>
                            <th>العميل</th>
                            <th>الفاتورة</th>
                            <th>المستودع</th>
                            <th>السيارة</th>
                            <th>المندوب</th>
                            <th>السبب</th>
                            <th class="number">عدد المواد</th>
                            <th class="number">المجموع</th>
                            <th class="number">الحسم</th>
                            <th class="number">الصافي</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($returns as $salesReturn)
                            <tr>
                                <td class="number">{{ $loop->iteration }}</td>

                                <td class="number">
                                    {{ $salesReturn->return_date?->format('Y-m-d') ?? '-' }}
                                </td>

                                <td class="number">
                                    {{ $salesReturn->return_number }}
                                </td>

                                <td>
                                    {{ $salesReturn->customer?->name ?? '-' }}
                                </td>

                                <td class="number">
                                    {{ $salesReturn->salesInvoice?->invoice_number ?? '-' }}
                                </td>

                                <td>
                                    {{ $salesReturn->warehouse?->name ?? '-' }}
                                </td>

                                <td class="number">
                                    {{ $salesReturn->vehicle?->plate_number ?? '-' }}
                                </td>

                                <td>
                                    {{ $salesReturn->salesRepresentative?->name ?? '-' }}
                                </td>

                                <td>
                                    {{ $reasonLabels[$salesReturn->return_reason]
                                        ?? ($salesReturn->return_reason ?: '-')
                                    }}
                                </td>

                                <td class="number">
                                    {{ number_format($salesReturn->items_count) }}
                                </td>

                                <td class="number">
                                    {{ $money($salesReturn->subtotal) }}
                                </td>

                                <td class="number">
                                    {{ $money($salesReturn->discount_amount) }}
                                </td>

                                <td class="number">
                                    {{ $money($salesReturn->total_amount) }}
                                </td>

                                <td>
                                    <span class="status {{ $statusClasses[$salesReturn->status] ?? '' }}">
                                        {{ $statusLabels[$salesReturn->status]
                                            ?? $salesReturn->status
                                        }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="14" class="empty">
                                    لا توجد مرتجعات مطابقة للفلاتر الحالية.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>

                    @if ($returns->isNotEmpty())
                        <tfoot>
                            <tr>
                                <td colspan="9">الإجمالي</td>

                                <td class="number">
                                    {{ number_format($totals['items_count']) }}
                                </td>

                                <td class="number">
                                    {{ $money($totals['subtotal']) }}
                                </td>

                                <td class="number">
                                    {{ $money($totals['discount_amount']) }}
                                </td>

                                <td class="number">
                                    {{ $money($totals['total_amount']) }}
                                </td>

                                <td></td>
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

            <span>FreshRoute — تقرير مرتجعات البيع</span>
        </footer>
    </main>
</body>
</html>