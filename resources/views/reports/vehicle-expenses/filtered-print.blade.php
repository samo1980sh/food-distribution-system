@php
    $money = fn ($value): string => number_format((float) $value, 2) . ' ل.س';
@endphp

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقرير مصاريف السيارات</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #eef2f4;
            color: #17252a;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 10px;
            line-height: 1.5;
        }
        .toolbar, .sheet {
            width: min(1600px, calc(100% - 30px));
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
        .print-button { background: #0f766e; color: #fff; }
        .close-button { background: #dce4e7; color: #26363b; }
        .sheet {
            margin-top: 14px;
            margin-bottom: 35px;
            padding: 24px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 12px 35px rgba(15, 35, 43, 0.1);
        }
        .report-header {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            padding-bottom: 14px;
            border-bottom: 3px solid #0f766e;
        }
        .brand h1, .document-title h2 { margin: 0; }
        .brand h1 { color: #0f766e; font-size: 23px; }
        .brand p, .document-title p { margin: 3px 0 0; color: #607278; }
        .document-title { text-align: left; }
        .section { margin-top: 16px; }
        .section-title {
            margin: 0 0 9px;
            padding-right: 9px;
            border-right: 4px solid #0f766e;
            font-size: 14px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px;
        }
        .stat {
            padding: 9px 11px;
            border: 1px solid #dbe3e6;
            border-radius: 8px;
            background: #f8fbfb;
        }
        .stat span { display: block; color: #64767c; font-size: 9px; }
        .stat strong {
            display: block;
            margin-top: 3px;
            color: #0f5f58;
            font-size: 14px;
            direction: ltr;
            text-align: right;
        }
        .filters, .types {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
        }
        .filter, .type-card {
            padding: 5px 9px;
            border: 1px solid #cfe1df;
            border-radius: 999px;
            background: #f1f8f7;
        }
        .filter b, .type-card b { color: #0f5f58; }
        .table-wrap { overflow-x: auto; }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 6px 4px;
            border: 1px solid #cfdadd;
            vertical-align: middle;
        }
        th {
            background: #edf5f4;
            color: #24433f;
            font-size: 8px;
            font-weight: 700;
            white-space: nowrap;
        }
        td { font-size: 8px; }
        td.number, th.number {
            direction: ltr;
            text-align: center;
            white-space: nowrap;
        }
        tbody tr:nth-child(even) { background: #fafcfc; }
        tfoot td { background: #edf5f4; font-weight: 700; }
        .badge {
            display: inline-block;
            min-width: 60px;
            padding: 3px 7px;
            border-radius: 999px;
            background: #e7f5f3;
            color: #0f5f58;
            font-weight: 700;
            text-align: center;
            white-space: nowrap;
        }
        .empty {
            padding: 28px;
            color: #64767c;
            text-align: center;
        }
        .report-footer {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-top: 18px;
            padding-top: 9px;
            border-top: 1px solid #dbe3e6;
            color: #718187;
            font-size: 9px;
        }
        @media print {
            @page { size: A4 landscape; margin: 7mm; }
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet {
                width: 100%;
                margin: 0;
                padding: 0;
                border-radius: 0;
                box-shadow: none;
            }
            thead { display: table-header-group; }
            tfoot { display: table-footer-group; }
            tr { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="print-button" type="button" onclick="window.print()">طباعة</button>
        <button class="close-button" type="button" onclick="window.close()">إغلاق</button>
    </div>

    <main class="sheet">
        <header class="report-header">
            <div class="brand">
                <h1>{{ config('app.name') }}</h1>
                <p>نظام إدارة توزيع المواد الغذائية</p>
            </div>
            <div class="document-title">
                <h2>تقرير مصاريف السيارات</h2>
                <p>المصاريف المعتمدة فقط</p>
            </div>
        </header>

        <section class="section">
            <h3 class="section-title">الإجماليات</h3>
            <div class="stats">
                <div class="stat">
                    <span>عدد المصاريف</span>
                    <strong>{{ number_format((int) $totals['count']) }}</strong>
                </div>
                <div class="stat">
                    <span>إجمالي المصاريف</span>
                    <strong>{{ $money($totals['total_amount']) }}</strong>
                </div>
                <div class="stat">
                    <span>المصاريف النقدية</span>
                    <strong>{{ $money($totals['cash_amount']) }}</strong>
                </div>
                <div class="stat">
                    <span>المصاريف غير النقدية</span>
                    <strong>{{ $money($totals['non_cash_amount']) }}</strong>
                </div>
            </div>
        </section>

        @if ($filterSummary !== [])
            <section class="section">
                <h3 class="section-title">الفلاتر المطبقة</h3>
                <div class="filters">
                    @foreach ($filterSummary as $label => $value)
                        <span class="filter"><b>{{ $label }}:</b> {{ $value }}</span>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($typeTotals->isNotEmpty())
            <section class="section">
                <h3 class="section-title">الإجماليات حسب نوع المصروف</h3>
                <div class="types">
                    @foreach ($typeTotals as $typeTotal)
                        <span class="type-card">
                            <b>{{ $expenseTypeLabels[$typeTotal->expense_type] ?? $typeTotal->expense_type }}:</b>
                            {{ number_format((int) $typeTotal->records_count) }} عملية —
                            {{ $money($typeTotal->total_amount) }}
                        </span>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="section">
            <h3 class="section-title">تفاصيل المصاريف</h3>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>رقم المصروف</th>
                            <th>التاريخ</th>
                            <th>السيارة</th>
                            <th>المستودع</th>
                            <th>الخط</th>
                            <th>السائق</th>
                            <th>المندوب</th>
                            <th>النوع</th>
                            <th>الدفع</th>
                            <th class="number">المبلغ</th>
                            <th>المعتمد بواسطة</th>
                            <th>تاريخ الاعتماد</th>
                            <th>الإيصال</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($expenses as $expense)
                            <tr>
                                <td>{{ $expense->expense_number }}</td>
                                <td class="number">{{ $expense->expense_date?->format('Y-m-d') ?? '-' }}</td>
                                <td>{{ $expense->vehicle?->plate_number ?? '-' }}</td>
                                <td>{{ $expense->warehouse?->name ?? '-' }}</td>
                                <td>{{ $expense->route?->name ?? '-' }}</td>
                                <td>{{ $expense->driver?->name ?? '-' }}</td>
                                <td>{{ $expense->salesRepresentative?->name ?? '-' }}</td>
                                <td>
                                    <span class="badge">
                                        {{ $expenseTypeLabels[$expense->expense_type] ?? $expense->expense_type }}
                                    </span>
                                </td>
                                <td>
                                    {{ $paymentMethodLabels[$expense->payment_method] ?? $expense->payment_method }}
                                </td>
                                <td class="number">{{ $money($expense->amount) }}</td>
                                <td>{{ $expense->approvedBy?->name ?? '-' }}</td>
                                <td class="number">{{ $expense->approved_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                <td>{{ filled($expense->receipt_path) ? 'مرفق' : 'غير مرفق' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="empty" colspan="13">لا توجد مصاريف معتمدة مطابقة للفلاتر الحالية.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="9">الإجمالي</td>
                            <td class="number">{{ $money($totals['total_amount']) }}</td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <footer class="report-footer">
            <span>تمت الطباعة بواسطة: {{ $generatedBy ?: '-' }}</span>
            <span>{{ now()->format('Y-m-d H:i') }}</span>
        </footer>
    </main>
</body>
</html>
