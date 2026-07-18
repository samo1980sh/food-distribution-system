@php
    $money = fn ($value): string => number_format((float) $value, 2) . ' ل.س';
    $quantity = fn ($value): string => number_format((float) $value, 3);

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

    <title>طباعة نتائج تقرير الإغلاق اليومي</title>

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
            color: white;
        }

        .close-button {
            background: #dce4e7;
            color: #26363b;
        }

        .sheet {
            margin-top: 14px;
            margin-bottom: 35px;
            padding: 28px;
            background: white;
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
            grid-template-columns: repeat(5, minmax(0, 1fr));
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

        .summary-card.difference {
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
                background: white;
                font-size: 8.5px;
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
                <h2>نتائج تقرير الإغلاق اليومي</h2>
                <p>عدد النتائج: {{ number_format($totals['count']) }}</p>
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
                    <span class="filter">جميع الإغلاقات</span>
                @endforelse
            </div>
        </section>

        <section class="section">
            <h3 class="section-title">الإجماليات</h3>

            <div class="summary-grid">
                <div class="summary-card">
                    <span>عدد الإغلاقات</span>
                    <strong>{{ number_format($totals['count']) }}</strong>
                </div>

                <div class="summary-card">
                    <span>إجمالي أرصدة البداية</span>
                    <strong>{{ $quantity($totals['total_opening_quantity']) }}</strong>
                </div>

                <div class="summary-card">
                    <span>إجمالي الوارد الدفتري</span>
                    <strong>{{ $quantity($totals['total_movement_in_quantity']) }}</strong>
                </div>

                <div class="summary-card">
                    <span>إجمالي الصادر الدفتري</span>
                    <strong>{{ $quantity($totals['total_movement_out_quantity']) }}</strong>
                </div>

                <div class="summary-card">
                    <span>إجمالي الرصيد المتوقع</span>
                    <strong>{{ $quantity($totals['total_expected_quantity']) }}</strong>
                </div>

                <div class="summary-card">
                    <span>إجمالي المبيعات</span>
                    <strong>{{ $money($totals['total_sales_amount']) }}</strong>
                </div>

                <div class="summary-card">
                    <span>إجمالي المرتجعات</span>
                    <strong>{{ $money($totals['total_returns_amount']) }}</strong>
                </div>

                <div class="summary-card">
                    <span>صافي المبيعات</span>
                    <strong>{{ $money($totals['net_sales_amount']) }}</strong>
                </div>

                <div class="summary-card">
                    <span>إجمالي التحصيلات</span>
                    <strong>{{ $money($totals['total_collections_amount']) }}</strong>
                </div>

                <div class="summary-card">
                    <span>مصاريف السيارات</span>
                    <strong>{{ $money($totals['total_vehicle_expenses_amount']) }}</strong>
                </div>

                <div class="summary-card">
                    <span>النقد المتوقع</span>
                    <strong>{{ $money($totals['expected_cash_amount']) }}</strong>
                </div>

                <div class="summary-card">
                    <span>النقد الفعلي</span>
                    <strong>{{ $money($totals['actual_cash_amount']) }}</strong>
                </div>

                <div class="summary-card difference">
                    <span>فرق الصندوق</span>
                    <strong>{{ $money($totals['cash_difference']) }}</strong>
                </div>
            </div>
        </section>

        <section class="section">
            <h3 class="section-title">تفاصيل الإغلاقات</h3>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>التاريخ</th>
                            <th>رقم الإغلاق</th>
                            <th>المستودع</th>
                            <th>السيارة</th>
                            <th>خط التوزيع</th>
                            <th>المندوب</th>
                            <th>الحالة</th>
                            <th class="number">المبيعات</th>
                            <th class="number">المرتجعات</th>
                            <th class="number">الصافي</th>
                            <th class="number">التحصيلات</th>
                            <th class="number">المصاريف</th>
                            <th class="number">المتوقع</th>
                            <th class="number">الفعلي</th>
                            <th class="number">الفرق</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($closings as $closing)
                            @php
                                $netSales = max(
                                    (float) $closing->total_sales_amount
                                    - (float) $closing->total_returns_amount,
                                    0,
                                );
                            @endphp

                            <tr>
                                <td class="number">{{ $loop->iteration }}</td>
                                <td class="number">{{ $closing->closing_date?->format('Y-m-d') ?? '-' }}</td>
                                <td class="number">{{ $closing->closing_number }}</td>
                                <td>{{ $closing->warehouse?->name ?? '-' }}</td>
                                <td class="number">{{ $closing->vehicle?->plate_number ?? '-' }}</td>
                                <td>{{ $closing->route?->name ?? '-' }}</td>
                                <td>{{ $closing->salesRepresentative?->name ?? '-' }}</td>

                                <td>
                                    <span class="status {{ $statusClasses[$closing->status] ?? '' }}">
                                        {{ $statusLabels[$closing->status] ?? $closing->status }}
                                    </span>
                                </td>

                                <td class="number">{{ $money($closing->total_sales_amount) }}</td>
                                <td class="number">{{ $money($closing->total_returns_amount) }}</td>
                                <td class="number">{{ $money($netSales) }}</td>
                                <td class="number">{{ $money($closing->total_collections_amount) }}</td>
                                <td class="number">{{ $money($closing->total_vehicle_expenses_amount) }}</td>
                                <td class="number">{{ $money($closing->expected_cash_amount) }}</td>
                                <td class="number">{{ $money($closing->actual_cash_amount) }}</td>
                                <td class="number">{{ $money($closing->cash_difference) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="16" class="empty">
                                    لا توجد إغلاقات مطابقة للفلاتر الحالية.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>

                    @if ($closings->isNotEmpty())
                        <tfoot>
                            <tr>
                                <td colspan="8">الإجمالي</td>
                                <td class="number">{{ $money($totals['total_sales_amount']) }}</td>
                                <td class="number">{{ $money($totals['total_returns_amount']) }}</td>
                                <td class="number">{{ $money($totals['net_sales_amount']) }}</td>
                                <td class="number">{{ $money($totals['total_collections_amount']) }}</td>
                                <td class="number">{{ $money($totals['total_vehicle_expenses_amount']) }}</td>
                                <td class="number">{{ $money($totals['expected_cash_amount']) }}</td>
                                <td class="number">{{ $money($totals['actual_cash_amount']) }}</td>
                                <td class="number">{{ $money($totals['cash_difference']) }}</td>
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

            <span>FreshRoute — تقرير الإغلاق اليومي</span>
        </footer>
    </main>
</body>
</html>