@php
    use App\Services\Reports\OverdueCustomerReportService;

    $money = fn ($value): string => number_format((float) $value, 2) . ' ل.س';
    $percent = fn ($value): string => $value === null
        ? '-'
        : number_format((float) $value, 1) . '%';
@endphp

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقرير العملاء المتأخرين بالدفع</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #eef2f4;
            color: #17252a;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 9px;
            line-height: 1.45;
        }
        .toolbar, .sheet {
            width: min(1700px, calc(100% - 30px));
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
            grid-template-columns: repeat(7, minmax(0, 1fr));
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
            font-size: 11px;
            direction: ltr;
            text-align: right;
        }
        .danger { background: #fef2f2; border-color: #fecaca; }
        .danger strong { color: #b91c1c; }
        .warning { background: #fffbeb; border-color: #fde68a; }
        .warning strong { color: #b45309; }
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
            font-size: 7px;
            white-space: nowrap;
        }
        td { font-size: 7px; }
        .number {
            direction: ltr;
            text-align: center;
            white-space: nowrap;
        }
        tbody tr:nth-child(even) { background: #fafcfc; }
        tbody tr.high { background: #fff7ed; }
        tbody tr.over-limit { background: #fef2f2; }
        tfoot td { background: #edf5f4; font-weight: 700; }
        .badge {
            display: inline-block;
            min-width: 75px;
            padding: 3px 6px;
            border-radius: 999px;
            font-weight: 700;
            text-align: center;
            white-space: nowrap;
        }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
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
                <h2>تقرير العملاء المتأخرين بالدفع</h2>
                <p>كما في {{ $settings['as_of'] }} — مدة السماح {{ $settings['credit_days'] }} يومًا</p>
            </div>
        </header>

        <section class="section">
            <h3 class="section-title">الإجماليات</h3>
            <div class="stats">
                <div class="stat"><span>عدد العملاء</span><strong>{{ number_format($totals['customers_count']) }}</strong></div>
                <div class="stat"><span>الرصيد الحالي</span><strong>{{ $money($totals['current_balance']) }}</strong></div>
                <div class="stat danger"><span>المبلغ المتأخر</span><strong>{{ $money($totals['overdue_amount']) }}</strong></div>
                <div class="stat"><span>غير المتأخر</span><strong>{{ $money($totals['not_due_amount']) }}</strong></div>
                <div class="stat"><span>الفواتير المتأخرة</span><strong>{{ number_format($totals['overdue_invoices_count']) }}</strong></div>
                <div class="stat warning"><span>مخاطر مرتفعة</span><strong>{{ number_format($totals['high_risk_count']) }}</strong></div>
                <div class="stat danger"><span>متجاوزو الحد</span><strong>{{ number_format($totals['over_limit_count']) }}</strong></div>
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
            <h3 class="section-title">تفاصيل العملاء</h3>
            <table>
                <thead>
                    <tr>
                        <th>الرمز</th>
                        <th>العميل</th>
                        <th>المنطقة</th>
                        <th>الخط</th>
                        <th>الهاتف</th>
                        <th>نوع الدفع</th>
                        <th class="number">الحد الائتماني</th>
                        <th class="number">الرصيد الحالي</th>
                        <th class="number">المتأخر</th>
                        <th class="number">غير المتأخر</th>
                        <th class="number">عدد الفواتير</th>
                        <th>أقدم مديونية</th>
                        <th class="number">أيام التأخير</th>
                        <th class="number">استخدام الحد</th>
                        <th>المخاطر</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $customer)
                        @php
                            $rowClass = match ($customer['risk_status']) {
                                'over_limit' => 'over-limit',
                                'high' => 'high',
                                default => '',
                            };

                            $badgeClass = match (
                                OverdueCustomerReportService::riskColor(
                                    $customer['risk_status']
                                )
                            ) {
                                'danger' => 'badge-danger',
                                'warning' => 'badge-warning',
                                default => 'badge-success',
                            };
                        @endphp
                        <tr class="{{ $rowClass }}">
                            <td>{{ $customer['customer']['code'] }}</td>
                            <td>{{ $customer['customer']['name'] }}</td>
                            <td>{{ $customer['customer']['area'] ?: '-' }}</td>
                            <td>{{ $customer['customer']['route'] ?: '-' }}</td>
                            <td>{{ $customer['customer']['mobile'] ?: ($customer['customer']['phone'] ?: '-') }}</td>
                            <td>{{ OverdueCustomerReportService::paymentTypeLabel($customer['customer']['payment_type']) }}</td>
                            <td class="number">{{ $money($customer['customer']['credit_limit']) }}</td>
                            <td class="number">{{ $money($customer['current_balance']) }}</td>
                            <td class="number">{{ $money($customer['overdue_amount']) }}</td>
                            <td class="number">{{ $money($customer['not_due_amount']) }}</td>
                            <td class="number">{{ number_format($customer['overdue_invoices_count']) }}</td>
                            <td class="number">{{ $customer['oldest_overdue_date'] ?: '-' }}</td>
                            <td class="number">{{ $customer['days_overdue'] ?: '-' }}</td>
                            <td class="number">{{ $percent($customer['credit_usage_percent']) }}</td>
                            <td>
                                <span class="badge {{ $badgeClass }}">
                                    {{ OverdueCustomerReportService::riskLabel($customer['risk_status']) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="empty" colspan="15">لا توجد مديونيات مطابقة للفلاتر الحالية.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="7">الإجمالي</td>
                        <td class="number">{{ $money($totals['current_balance']) }}</td>
                        <td class="number">{{ $money($totals['overdue_amount']) }}</td>
                        <td class="number">{{ $money($totals['not_due_amount']) }}</td>
                        <td class="number">{{ number_format($totals['overdue_invoices_count']) }}</td>
                        <td colspan="4"></td>
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
