@php
    use App\Services\Reports\TopCustomerReportService;

    $money = fn ($value): string => number_format((float) $value, 2) . ' ل.س';
    $quantity = fn ($value): string => number_format((float) $value, 3);
    $percent = fn ($value): string => $value === null
        ? '-'
        : number_format((float) $value, 1) . '%';
@endphp

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقرير العملاء الأكثر شراءً</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #eef2f4;
            color: #17252a;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 8px;
            line-height: 1.45;
        }
        .toolbar, .sheet {
            width: min(1750px, calc(100% - 30px));
            margin-right: auto;
            margin-left: auto;
        }
        .toolbar { display: flex; gap: 10px; margin-top: 18px; }
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
            padding: 22px;
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
        .brand h1 { color: #0f766e; font-size: 22px; }
        .brand p, .document-title p { margin: 3px 0 0; color: #607278; }
        .document-title { text-align: left; }
        .section { margin-top: 15px; }
        .section-title {
            margin: 0 0 8px;
            padding-right: 8px;
            border-right: 4px solid #0f766e;
            font-size: 13px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(8, minmax(0, 1fr));
            gap: 7px;
        }
        .stat {
            padding: 8px 9px;
            border: 1px solid #dbe3e6;
            border-radius: 8px;
            background: #f8fbfb;
        }
        .stat span { display: block; color: #64767c; font-size: 7px; }
        .stat strong {
            display: block;
            margin-top: 3px;
            color: #0f5f58;
            font-size: 10px;
            direction: ltr;
            text-align: right;
        }
        .danger { background: #fef2f2; border-color: #fecaca; }
        .danger strong { color: #b91c1c; }
        .filters { display: flex; flex-wrap: wrap; gap: 7px; }
        .filter {
            padding: 5px 9px;
            border: 1px solid #cfe1df;
            border-radius: 999px;
            background: #f1f8f7;
        }
        .filter b { color: #0f5f58; }
        table { width: 100%; border-collapse: collapse; }
        th, td {
            padding: 5px 4px;
            border: 1px solid #cfdadd;
            vertical-align: middle;
        }
        th {
            background: #edf5f4;
            color: #24433f;
            font-size: 6.7px;
            white-space: nowrap;
        }
        td { font-size: 6.7px; }
        .number {
            direction: ltr;
            text-align: center;
            white-space: nowrap;
        }
        tbody tr:nth-child(even) { background: #fafcfc; }
        tbody tr.first { background: #fffbeb; }
        tbody tr.loss { background: #fef2f2; }
        tfoot td { background: #edf5f4; font-weight: 700; }
        .rank {
            display: inline-block;
            min-width: 25px;
            padding: 3px 6px;
            border-radius: 999px;
            background: #dbeafe;
            color: #1e40af;
            font-weight: 700;
            text-align: center;
        }
        .rank.first { background: #fef3c7; color: #92400e; }
        .empty { padding: 26px; text-align: center; color: #64767c; }
        .report-footer {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-top: 18px;
            padding-top: 9px;
            border-top: 1px solid #dbe3e6;
            color: #718187;
            font-size: 8px;
        }
        @media print {
            @page { size: A4 landscape; margin: 6mm; }
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
                <h2>تقرير العملاء الأكثر شراءً</h2>
                <p>{{ $settings['from'] }} — {{ $settings['until'] }}</p>
            </div>
        </header>

        <section class="section">
            <h3 class="section-title">الإجماليات</h3>
            <div class="stats">
                <div class="stat"><span>عدد العملاء</span><strong>{{ number_format($totals['customers_count']) }}</strong></div>
                <div class="stat"><span>عدد الفواتير</span><strong>{{ number_format($totals['invoice_count']) }}</strong></div>
                <div class="stat"><span>عدد المرتجعات</span><strong>{{ number_format($totals['return_count']) }}</strong></div>
                <div class="stat"><span>إجمالي المبيعات</span><strong>{{ $money($totals['gross_sales']) }}</strong></div>
                <div class="stat"><span>قيمة المرتجعات</span><strong>{{ $money($totals['returns_amount']) }}</strong></div>
                <div class="stat"><span>صافي المبيعات</span><strong>{{ $money($totals['net_sales']) }}</strong></div>
                <div class="stat"><span>صافي الكمية</span><strong>{{ $quantity($totals['net_quantity']) }}</strong></div>
                <div class="stat {{ $totals['approximate_profit'] < 0 ? 'danger' : '' }}"><span>الربح التقريبي</span><strong>{{ $money($totals['approximate_profit']) }}</strong></div>
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

        <section class="section">
            <h3 class="section-title">ترتيب العملاء</h3>
            <table>
                <thead>
                    <tr>
                        <th>الترتيب</th>
                        <th>الرمز</th>
                        <th>العميل</th>
                        <th>المنطقة</th>
                        <th>الخط</th>
                        <th>نوع العميل</th>
                        <th class="number">الفواتير</th>
                        <th class="number">إجمالي المبيعات</th>
                        <th class="number">المرتجعات</th>
                        <th class="number">قيمة المرتجعات</th>
                        <th class="number">صافي المبيعات</th>
                        <th class="number">صافي الكمية</th>
                        <th class="number">متوسط الفاتورة</th>
                        <th class="number">الربح التقريبي</th>
                        <th class="number">هامش الربح</th>
                        <th class="number">الحصة من الصافي</th>
                        <th>آخر شراء</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rankings as $customer)
                        <tr class="{{ $customer['rank'] === 1 ? 'first' : '' }} {{ $customer['approximate_profit'] < 0 ? 'loss' : '' }}">
                            <td class="number">
                                <span class="rank {{ $customer['rank'] === 1 ? 'first' : '' }}">
                                    {{ $customer['rank'] }}
                                </span>
                            </td>
                            <td>{{ $customer['customer']['code'] }}</td>
                            <td>{{ $customer['customer']['name'] }}</td>
                            <td>{{ $customer['customer']['area'] ?: '-' }}</td>
                            <td>{{ $customer['customer']['route'] ?: '-' }}</td>
                            <td>{{ TopCustomerReportService::customerTypeLabel($customer['customer']['customer_type']) }}</td>
                            <td class="number">{{ number_format($customer['invoice_count']) }}</td>
                            <td class="number">{{ $money($customer['gross_sales']) }}</td>
                            <td class="number">{{ number_format($customer['return_count']) }}</td>
                            <td class="number">{{ $money($customer['returns_amount']) }}</td>
                            <td class="number">{{ $money($customer['net_sales']) }}</td>
                            <td class="number">{{ $quantity($customer['net_quantity']) }}</td>
                            <td class="number">{{ $money($customer['average_invoice']) }}</td>
                            <td class="number">{{ $money($customer['approximate_profit']) }}</td>
                            <td class="number">{{ $percent($customer['profit_margin_percent']) }}</td>
                            <td class="number">{{ $percent($customer['net_sales_share_percent']) }}</td>
                            <td class="number">{{ $customer['last_purchase_date'] ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="empty" colspan="17">لا توجد مبيعات صافية موجبة مطابقة للفلاتر الحالية.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="6">الإجمالي</td>
                        <td class="number">{{ number_format($totals['invoice_count']) }}</td>
                        <td class="number">{{ $money($totals['gross_sales']) }}</td>
                        <td class="number">{{ number_format($totals['return_count']) }}</td>
                        <td class="number">{{ $money($totals['returns_amount']) }}</td>
                        <td class="number">{{ $money($totals['net_sales']) }}</td>
                        <td class="number">{{ $quantity($totals['net_quantity']) }}</td>
                        <td></td>
                        <td class="number">{{ $money($totals['approximate_profit']) }}</td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
            </table>
        </section>

        <footer class="report-footer">
            <span>تمت الطباعة بواسطة: {{ $generatedBy ?: '-' }}</span>
            <span>{{ now()->format('Y-m-d H:i') }}</span>
        </footer>
    </main>
</body>
</html>
