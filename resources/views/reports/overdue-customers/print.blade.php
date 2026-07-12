@php
    use App\Services\Reports\OverdueCustomerReportService;

    $money = fn ($value): string => number_format((float) $value, 2) . ' ل.س';
    $percent = fn ($value): string => $value === null
        ? 'لا يوجد حد'
        : number_format((float) $value, 1) . '%';
@endphp

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>كشف مديونية {{ $report['customer']['name'] }}</title>
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
            width: min(1500px, calc(100% - 30px));
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
        .stats {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 8px;
        }
        .stat, .field {
            padding: 9px 10px;
            border: 1px solid #dbe3e6;
            border-radius: 8px;
            background: #f8fbfb;
        }
        .stat span, .field span { display: block; color: #64767c; font-size: 8px; }
        .stat strong, .field strong { display: block; margin-top: 3px; }
        .stat strong {
            color: #0f5f58;
            font-size: 13px;
            direction: ltr;
            text-align: right;
        }
        .danger { background: #fef2f2; border-color: #fecaca; }
        .danger strong { color: #b91c1c; }
        .warning { background: #fffbeb; border-color: #fde68a; }
        .warning strong { color: #b45309; }
        .grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            background: #fee2e2;
            color: #991b1b;
            font-weight: 700;
        }
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
        tbody tr.overdue { background: #fff7ed; }
        .empty { padding: 20px; text-align: center; color: #64767c; }
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
                <h2>كشف مديونية عميل</h2>
                <p>{{ $report['customer']['code'] }} — {{ $report['customer']['name'] }}</p>
            </div>
        </header>

        <section class="section">
            <div class="grid">
                <div class="field"><span>العميل</span><strong>{{ $report['customer']['name'] }}</strong></div>
                <div class="field"><span>صاحب المنشأة</span><strong>{{ $report['customer']['owner_name'] ?: '-' }}</strong></div>
                <div class="field"><span>المنطقة</span><strong>{{ $report['customer']['area'] ?: '-' }}</strong></div>
                <div class="field"><span>خط التوزيع</span><strong>{{ $report['customer']['route'] ?: '-' }}</strong></div>
                <div class="field"><span>الهاتف</span><strong>{{ $report['customer']['mobile'] ?: ($report['customer']['phone'] ?: '-') }}</strong></div>
                <div class="field"><span>نوع الدفع</span><strong>{{ OverdueCustomerReportService::paymentTypeLabel($report['customer']['payment_type']) }}</strong></div>
                <div class="field"><span>مدة السماح</span><strong>{{ $report['credit_days'] }} يومًا</strong></div>
                <div class="field"><span>كما في تاريخ</span><strong>{{ $report['as_of'] }}</strong></div>
            </div>
        </section>

        <section class="section">
            <h3 class="section-title">ملخص المديونية</h3>
            <div class="stats">
                <div class="stat"><span>الرصيد الحالي</span><strong>{{ $money($report['current_balance']) }}</strong></div>
                <div class="stat danger"><span>المبلغ المتأخر</span><strong>{{ $money($report['overdue_amount']) }}</strong></div>
                <div class="stat"><span>غير المتأخر</span><strong>{{ $money($report['not_due_amount']) }}</strong></div>
                <div class="stat warning"><span>أيام التأخير</span><strong>{{ number_format($report['days_overdue']) }}</strong></div>
                <div class="stat"><span>استخدام الحد الائتماني</span><strong>{{ $percent($report['credit_usage_percent']) }}</strong></div>
            </div>
        </section>

        <section class="section">
            <div class="grid">
                <div class="field"><span>الحد الائتماني</span><strong>{{ $money($report['customer']['credit_limit']) }}</strong></div>
                <div class="field"><span>الفواتير المتأخرة</span><strong>{{ number_format($report['overdue_invoices_count']) }}</strong></div>
                <div class="field"><span>أقدم مديونية متأخرة</span><strong>{{ $report['oldest_overdue_date'] ?: '-' }}</strong></div>
                <div class="field">
                    <span>حالة المخاطر</span>
                    <strong><span class="badge">{{ OverdueCustomerReportService::riskLabel($report['risk_status']) }}</span></strong>
                </div>
            </div>
        </section>

        <section class="section">
            <h3 class="section-title">توزيع الرصيد على الفواتير وفق FIFO</h3>
            <table>
                <thead>
                    <tr>
                        <th>الفاتورة</th>
                        <th>التاريخ</th>
                        <th>الاستحقاق المحسوب</th>
                        <th>نوع الدفع</th>
                        <th class="number">الإجمالي</th>
                        <th class="number">دفعة الفاتورة</th>
                        <th class="number">الحسميات الموزعة</th>
                        <th class="number">المتبقي</th>
                        <th class="number">المتأخر</th>
                        <th class="number">أيام التأخير</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report['invoices'] as $invoice)
                        <tr class="{{ $invoice['is_overdue'] ? 'overdue' : '' }}">
                            <td>{{ $invoice['invoice_number'] }}</td>
                            <td class="number">{{ $invoice['invoice_date'] }}</td>
                            <td class="number">{{ $invoice['due_date'] }}</td>
                            <td>{{ OverdueCustomerReportService::paymentTypeLabel($invoice['payment_type']) }}</td>
                            <td class="number">{{ $money($invoice['total_amount']) }}</td>
                            <td class="number">{{ $money($invoice['invoice_cash_amount']) }}</td>
                            <td class="number">{{ $money($invoice['allocated_credits']) }}</td>
                            <td class="number">{{ $money($invoice['remaining_amount']) }}</td>
                            <td class="number">{{ $money($invoice['overdue_amount']) }}</td>
                            <td class="number">{{ $invoice['days_overdue'] ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr><td class="empty" colspan="10">لا توجد فواتير معتمدة حتى تاريخ التقرير.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="section">
            <h3 class="section-title">التحصيلات المعتمدة حتى تاريخ التقرير</h3>
            <table>
                <thead>
                    <tr>
                        <th>رقم التحصيل</th>
                        <th>التاريخ</th>
                        <th>الفاتورة المرتبطة</th>
                        <th>الطريقة</th>
                        <th>المرجع</th>
                        <th class="number">المبلغ</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report['payments'] as $payment)
                        <tr>
                            <td>{{ $payment['document_number'] }}</td>
                            <td class="number">{{ $payment['date'] }}</td>
                            <td>{{ $payment['invoice_number'] ?: '-' }}</td>
                            <td>{{ OverdueCustomerReportService::paymentMethodLabel($payment['payment_method']) }}</td>
                            <td>{{ $payment['reference_number'] ?: '-' }}</td>
                            <td class="number">{{ $money($payment['amount']) }}</td>
                        </tr>
                    @empty
                        <tr><td class="empty" colspan="6">لا توجد تحصيلات معتمدة.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="section">
            <h3 class="section-title">المرتجعات المعتمدة حتى تاريخ التقرير</h3>
            <table>
                <thead>
                    <tr>
                        <th>رقم المرتجع</th>
                        <th>التاريخ</th>
                        <th>الفاتورة الأصلية</th>
                        <th>السبب</th>
                        <th class="number">القيمة</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report['returns'] as $return)
                        <tr>
                            <td>{{ $return['document_number'] }}</td>
                            <td class="number">{{ $return['date'] }}</td>
                            <td>{{ $return['invoice_number'] ?: '-' }}</td>
                            <td>{{ $return['reason'] ?: '-' }}</td>
                            <td class="number">{{ $money($return['amount']) }}</td>
                        </tr>
                    @empty
                        <tr><td class="empty" colspan="5">لا توجد مرتجعات معتمدة.</td></tr>
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
