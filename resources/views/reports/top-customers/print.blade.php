@php
    use App\Services\Reports\TopCustomerReportService;

    $summary = $report['summary'];
    $settings = $report['settings'];

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
    <title>تفصيل مشتريات {{ $summary['customer']['name'] }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #eef2f4;
            color: #17252a;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 9px;
            line-height: 1.5;
        }
        .toolbar, .sheet {
            width: min(1550px, calc(100% - 30px));
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
        h1, h2, h3, p { margin-top: 0; }
        .brand h1 { margin-bottom: 3px; color: #0f766e; font-size: 23px; }
        .brand p, .document-title p { margin-bottom: 0; color: #607278; }
        .document-title { text-align: left; }
        .document-title h2 { margin-bottom: 3px; font-size: 19px; }
        .section { margin-top: 16px; }
        .section-title {
            margin-bottom: 8px;
            padding-right: 8px;
            border-right: 4px solid #0f766e;
            font-size: 14px;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px;
        }
        .field, .stat {
            padding: 9px 10px;
            border: 1px solid #dbe3e6;
            border-radius: 8px;
            background: #f8fbfb;
        }
        .field span, .stat span {
            display: block;
            color: #64767c;
            font-size: 8px;
        }
        .field strong, .stat strong { display: block; margin-top: 3px; }
        .stats {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 7px;
        }
        .stat strong {
            color: #0f5f58;
            font-size: 12px;
            direction: ltr;
            text-align: right;
        }
        .danger { background: #fef2f2; border-color: #fecaca; }
        .danger strong { color: #b91c1c; }
        table { width: 100%; border-collapse: collapse; }
        th, td {
            padding: 6px 5px;
            border: 1px solid #cfdadd;
            vertical-align: middle;
        }
        th {
            background: #edf5f4;
            color: #24433f;
            font-size: 8px;
            white-space: nowrap;
        }
        td { font-size: 8px; }
        .number {
            direction: ltr;
            text-align: center;
            white-space: nowrap;
        }
        tbody tr:nth-child(even) { background: #fafcfc; }
        .empty { padding: 22px; text-align: center; color: #64767c; }
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
                <h2>تفصيل مشتريات عميل</h2>
                <p>{{ $summary['customer']['code'] }} — {{ $summary['customer']['name'] }}</p>
            </div>
        </header>

        <section class="section">
            <div class="grid">
                <div class="field"><span>العميل</span><strong>{{ $summary['customer']['name'] }}</strong></div>
                <div class="field"><span>نوع العميل</span><strong>{{ TopCustomerReportService::customerTypeLabel($summary['customer']['customer_type']) }}</strong></div>
                <div class="field"><span>المنطقة</span><strong>{{ $summary['customer']['area'] ?: '-' }}</strong></div>
                <div class="field"><span>خط التوزيع</span><strong>{{ $summary['customer']['route'] ?: '-' }}</strong></div>
                <div class="field"><span>الهاتف</span><strong>{{ $summary['customer']['mobile'] ?: ($summary['customer']['phone'] ?: '-') }}</strong></div>
                <div class="field"><span>نمط الدفع</span><strong>{{ TopCustomerReportService::paymentTypeLabel($summary['customer']['payment_type']) }}</strong></div>
                <div class="field"><span>الفترة</span><strong>{{ $settings['from'] }} — {{ $settings['until'] }}</strong></div>
                <div class="field"><span>آخر عملية شراء</span><strong>{{ $summary['last_purchase_date'] ?: '-' }}</strong></div>
            </div>
        </section>

        <section class="section">
            <h3 class="section-title">ملخص المشتريات</h3>
            <div class="stats">
                <div class="stat"><span>عدد الفواتير</span><strong>{{ number_format($summary['invoice_count']) }}</strong></div>
                <div class="stat"><span>إجمالي المبيعات</span><strong>{{ $money($summary['gross_sales']) }}</strong></div>
                <div class="stat"><span>قيمة المرتجعات</span><strong>{{ $money($summary['returns_amount']) }}</strong></div>
                <div class="stat"><span>صافي المبيعات</span><strong>{{ $money($summary['net_sales']) }}</strong></div>
                <div class="stat"><span>صافي الكمية</span><strong>{{ $quantity($summary['net_quantity']) }}</strong></div>
                <div class="stat {{ $summary['approximate_profit'] < 0 ? 'danger' : '' }}"><span>الربح التقريبي</span><strong>{{ $money($summary['approximate_profit']) }}</strong></div>
                <div class="stat {{ ($summary['profit_margin_percent'] ?? 0) < 0 ? 'danger' : '' }}"><span>هامش الربح</span><strong>{{ $percent($summary['profit_margin_percent']) }}</strong></div>
            </div>
        </section>

        <section class="section">
            <h3 class="section-title">الفواتير المعتمدة خلال الفترة</h3>
            <table>
                <thead>
                    <tr>
                        <th>رقم الفاتورة</th>
                        <th>التاريخ</th>
                        <th>نوع الدفع</th>
                        <th>السيارة</th>
                        <th>الخط</th>
                        <th>المندوب</th>
                        <th class="number">عدد البنود</th>
                        <th class="number">الكمية</th>
                        <th class="number">الإجمالي</th>
                        <th class="number">التكلفة</th>
                        <th class="number">الربح</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report['invoices'] as $invoice)
                        <tr>
                            <td>{{ $invoice['invoice_number'] }}</td>
                            <td class="number">{{ $invoice['invoice_date'] }}</td>
                            <td>{{ TopCustomerReportService::paymentTypeLabel($invoice['payment_type']) }}</td>
                            <td>{{ $invoice['vehicle'] ?: '-' }}</td>
                            <td>{{ $invoice['route'] ?: '-' }}</td>
                            <td>{{ $invoice['representative'] ?: '-' }}</td>
                            <td class="number">{{ number_format($invoice['items_count']) }}</td>
                            <td class="number">{{ $quantity($invoice['quantity']) }}</td>
                            <td class="number">{{ $money($invoice['total_amount']) }}</td>
                            <td class="number">{{ $money($invoice['cost_amount']) }}</td>
                            <td class="number">{{ $money($invoice['profit_amount']) }}</td>
                        </tr>
                    @empty
                        <tr><td class="empty" colspan="11">لا توجد فواتير معتمدة خلال الفترة.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="section">
            <h3 class="section-title">المرتجعات المعتمدة خلال الفترة</h3>
            <table>
                <thead>
                    <tr>
                        <th>رقم المرتجع</th>
                        <th>التاريخ</th>
                        <th>الفاتورة الأصلية</th>
                        <th>السبب</th>
                        <th class="number">عدد البنود</th>
                        <th class="number">الكمية</th>
                        <th class="number">قيمة المرتجع</th>
                        <th class="number">تكلفة المرتجع</th>
                        <th class="number">الربح المعكوس</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report['returns'] as $return)
                        <tr>
                            <td>{{ $return['return_number'] }}</td>
                            <td class="number">{{ $return['return_date'] }}</td>
                            <td>{{ $return['invoice_number'] ?: '-' }}</td>
                            <td>{{ $return['reason'] ?: '-' }}</td>
                            <td class="number">{{ number_format($return['items_count']) }}</td>
                            <td class="number">{{ $quantity($return['quantity']) }}</td>
                            <td class="number">{{ $money($return['total_amount']) }}</td>
                            <td class="number">{{ $money($return['cost_amount']) }}</td>
                            <td class="number">{{ $money($return['profit_reversal']) }}</td>
                        </tr>
                    @empty
                        <tr><td class="empty" colspan="9">لا توجد مرتجعات معتمدة خلال الفترة.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <footer class="report-footer">
            <span>تمت الطباعة بواسطة: {{ $generatedBy ?: '-' }}</span>
            <span>{{ now()->format('Y-m-d H:i') }}</span>
        </footer>
    </main>
</body>
</html>
