@php
    $money = fn ($value): string => number_format((float) $value, 2) . ' ل.س';

    $typeClasses = [
        'sales_invoice' => 'type-invoice',
        'customer_payment' => 'type-payment',
        'sales_return' => 'type-return',
    ];
@endphp

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        كشف حساب {{ $customer['name'] ?? '' }}
    </title>

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
            width: min(1400px, calc(100% - 30px));
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
            margin-top: 21px;
        }

        .section-title {
            margin: 0 0 11px;
            padding-right: 9px;
            border-right: 4px solid #0f766e;
            font-size: 16px;
        }

        .customer-grid,
        .summary-grid {
            display: grid;
            gap: 9px;
        }

        .customer-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .summary-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .customer-item,
        .summary-card {
            padding: 9px 11px;
            border: 1px solid #dbe3e6;
            border-radius: 8px;
        }

        .customer-item span,
        .summary-card span {
            display: block;
            color: #64767c;
            font-size: 11px;
        }

        .customer-item strong,
        .summary-card strong {
            display: block;
            margin-top: 3px;
            font-size: 13px;
        }

        .summary-card strong {
            direction: ltr;
            text-align: right;
            font-size: 14px;
        }

        .summary-card.closing {
            border-color: #0f766e;
            background: #f3faf9;
        }

        .period-box {
            display: inline-block;
            padding: 5px 11px;
            border: 1px solid #cfdadd;
            border-radius: 999px;
            background: #f8fafb;
        }

        .period-box strong {
            color: #0f766e;
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

        .type {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-weight: 700;
            white-space: nowrap;
        }

        .type-invoice {
            background: #fef3c7;
            color: #92400e;
        }

        .type-payment {
            background: #dcfce7;
            color: #166534;
        }

        .type-return {
            background: #dbeafe;
            color: #1e40af;
        }

        .opening-row td,
        tfoot td {
            background: #edf5f4;
            font-weight: 700;
        }

        .notes {
            display: block;
            margin-top: 3px;
            color: #64767c;
            font-size: 10px;
        }

        .empty {
            padding: 28px;
            text-align: center;
            color: #64767c;
        }

        .signatures {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 30px;
            margin-top: 45px;
        }

        .signature {
            min-height: 70px;
            padding-top: 8px;
            border-top: 1px solid #64767c;
            text-align: center;
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
            margin: 8mm;
        }

        @media print {
            body {
                background: #fff;
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

            .customer-grid,
            .summary-grid {
                gap: 5px;
            }

            .customer-item,
            .summary-card {
                padding: 5px 6px;
            }

            .customer-item span,
            .summary-card span {
                font-size: 7.5px;
            }

            .customer-item strong,
            .summary-card strong {
                font-size: 9px;
            }

            th,
            td {
                padding: 3px;
            }

            tr,
            .customer-item,
            .summary-card {
                break-inside: avoid;
            }

            .signatures {
                margin-top: 30px;
            }

            .report-footer {
                margin-top: 10px;
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
            طباعة كشف الحساب
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
                <h2>كشف حساب عميل</h2>
                <p>
                    {{ $customer['name'] ?? '-' }}
                    —
                    <span dir="ltr">{{ $customer['code'] ?? '-' }}</span>
                </p>
            </div>
        </header>

        <section class="section">
            <span class="period-box">
                <strong>الفترة:</strong>
                <span dir="ltr">{{ $from }}</span>
                —
                <span dir="ltr">{{ $until }}</span>
            </span>
        </section>

        <section class="section">
            <h3 class="section-title">بيانات العميل</h3>

            <div class="customer-grid">
                <div class="customer-item">
                    <span>اسم العميل</span>
                    <strong>{{ $customer['name'] ?? '-' }}</strong>
                </div>

                <div class="customer-item">
                    <span>اسم المالك</span>
                    <strong>{{ $customer['owner_name'] ?: '-' }}</strong>
                </div>

                <div class="customer-item">
                    <span>الهاتف</span>
                    <strong dir="ltr">
                        {{ $customer['mobile'] ?: ($customer['phone'] ?: '-') }}
                    </strong>
                </div>

                <div class="customer-item">
                    <span>المنطقة</span>
                    <strong>{{ $customer['area'] ?: '-' }}</strong>
                </div>

                <div class="customer-item">
                    <span>خط التوزيع</span>
                    <strong>{{ $customer['route'] ?: '-' }}</strong>
                </div>

                <div class="customer-item">
                    <span>العنوان</span>
                    <strong>{{ $customer['address'] ?: '-' }}</strong>
                </div>

                <div class="customer-item">
                    <span>الحد الائتماني</span>
                    <strong dir="ltr">
                        {{ $money($customer['credit_limit']) }}
                    </strong>
                </div>

                <div class="customer-item">
                    <span>عدد الحركات</span>
                    <strong>{{ number_format($totals['transaction_count']) }}</strong>
                </div>
            </div>
        </section>

        <section class="section">
            <h3 class="section-title">ملخص الحساب</h3>

            <div class="summary-grid">
                <div class="summary-card">
                    <span>الرصيد الافتتاحي</span>
                    <strong>{{ $money($totals['opening_balance']) }}</strong>
                </div>

                <div class="summary-card">
                    <span>إجمالي المدين</span>
                    <strong>{{ $money($totals['period_debit']) }}</strong>
                </div>

                <div class="summary-card">
                    <span>إجمالي الدائن</span>
                    <strong>{{ $money($totals['period_credit']) }}</strong>
                </div>

                <div class="summary-card closing">
                    <span>الرصيد الختامي</span>
                    <strong>{{ $money($totals['closing_balance']) }}</strong>
                </div>

                <div class="summary-card">
                    <span>فواتير البيع</span>
                    <strong>{{ $money($totals['sales_total']) }}</strong>
                </div>

                <div class="summary-card">
                    <span>نقد الفواتير</span>
                    <strong>{{ $money($totals['invoice_cash_total']) }}</strong>
                </div>

                <div class="summary-card">
                    <span>تحصيلات العملاء</span>
                    <strong>{{ $money($totals['payments_total']) }}</strong>
                </div>

                <div class="summary-card">
                    <span>مرتجعات البيع</span>
                    <strong>{{ $money($totals['returns_total']) }}</strong>
                </div>
            </div>
        </section>

        <section class="section">
            <h3 class="section-title">حركة الحساب</h3>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>التاريخ</th>
                            <th>نوع الحركة</th>
                            <th>رقم المستند</th>
                            <th>البيان</th>
                            <th class="number">مدين</th>
                            <th class="number">دائن</th>
                            <th class="number">الرصيد</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr class="opening-row">
                            <td class="number">-</td>
                            <td class="number">{{ $from }}</td>
                            <td colspan="5">الرصيد الافتتاحي قبل بداية الفترة</td>
                            <td class="number">
                                {{ $money($totals['opening_balance']) }}
                            </td>
                        </tr>

                        @forelse ($transactions as $transaction)
                            <tr>
                                <td class="number">{{ $loop->iteration }}</td>
                                <td class="number">{{ $transaction['date'] }}</td>

                                <td>
                                    <span class="type {{ $typeClasses[$transaction['type']] ?? '' }}">
                                        {{ $transaction['type_label'] }}
                                    </span>
                                </td>

                                <td class="number">
                                    {{ $transaction['document_number'] }}
                                </td>

                                <td>
                                    {{ $transaction['description'] }}

                                    @if (filled($transaction['notes']))
                                        <span class="notes">
                                            {{ $transaction['notes'] }}
                                        </span>
                                    @endif
                                </td>

                                <td class="number">
                                    {{ (float) $transaction['debit'] > 0
                                        ? $money($transaction['debit'])
                                        : '-'
                                    }}
                                </td>

                                <td class="number">
                                    {{ (float) $transaction['credit'] > 0
                                        ? $money($transaction['credit'])
                                        : '-'
                                    }}
                                </td>

                                <td class="number">
                                    {{ $money($transaction['balance']) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="empty">
                                    لا توجد حركات معتمدة ضمن الفترة المحددة.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>

                    <tfoot>
                        <tr>
                            <td colspan="5">إجمالي الفترة والرصيد الختامي</td>

                            <td class="number">
                                {{ $money($totals['period_debit']) }}
                            </td>

                            <td class="number">
                                {{ $money($totals['period_credit']) }}
                            </td>

                            <td class="number">
                                {{ $money($totals['closing_balance']) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <section class="signatures">
            <div class="signature">توقيع العميل</div>
            <div class="signature">توقيع المحاسب</div>
            <div class="signature">اعتماد الإدارة</div>
        </section>

        <footer class="report-footer">
            <span>
                أُنشئ بواسطة:
                {{ $generatedBy ?? '-' }}
            </span>

            <span>
                تاريخ الطباعة:
                {{ now()->format('Y-m-d H:i') }}
            </span>

            <span>FreshRoute — كشف حساب العميل</span>
        </footer>
    </main>
</body>
</html>