@php
    $money = fn ($value): string => number_format((float) $value, 2) . ' ل.س';
    $quantity = fn ($value): string => number_format((float) $value, 3);
    $percent = fn ($value): string => number_format((float) $value, 2) . '%';
@endphp

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>تقرير الأرباح التقريبية</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #eef2f4;
            color: #17252a;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 10px;
            line-height: 1.5;
        }

        .toolbar,
        .sheet {
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

        .brand h1,
        .document-title h2 {
            margin: 0;
        }

        .brand h1 {
            color: #0f766e;
            font-size: 23px;
        }

        .brand p,
        .document-title p {
            margin: 3px 0 0;
            color: #607278;
        }

        .document-title {
            text-align: left;
        }

        .section {
            margin-top: 16px;
        }

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

        .stat span {
            display: block;
            color: #64767c;
            font-size: 9px;
        }

        .stat strong {
            display: block;
            margin-top: 3px;
            color: #0f5f58;
            font-size: 14px;
            direction: ltr;
            text-align: right;
        }

        .stat strong.negative {
            color: #b91c1c;
        }

        .stat strong.positive {
            color: #15803d;
        }

        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
        }

        .filter {
            padding: 5px 9px;
            border: 1px solid #cfe1df;
            border-radius: 999px;
            background: #f1f8f7;
        }

        .filter b {
            color: #0f5f58;
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

        td {
            font-size: 8px;
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

        tbody tr.return-row {
            background: #fff7f7;
        }

        tfoot td {
            background: #edf5f4;
            font-weight: 700;
        }

        .badge {
            display: inline-block;
            min-width: 68px;
            padding: 3px 7px;
            border-radius: 999px;
            font-weight: 700;
            text-align: center;
            white-space: nowrap;
        }

        .badge-invoice {
            background: #dcfce7;
            color: #166534;
        }

        .badge-return {
            background: #fee2e2;
            color: #991b1b;
        }

        .positive {
            color: #15803d;
        }

        .negative {
            color: #b91c1c;
        }

        .empty {
            padding: 28px;
            color: #64767c;
            text-align: center;
        }

        .note {
            margin-top: 11px;
            padding: 7px 9px;
            border-right: 4px solid #d97706;
            background: #fffbeb;
            color: #784b0b;
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

        @page {
            size: A4 landscape;
            margin: 8mm;
        }

        @media print {
            body {
                background: #fff;
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }

            .toolbar {
                display: none;
            }

            .sheet {
                width: 100%;
                margin: 0;
                padding: 0;
                border-radius: 0;
                box-shadow: none;
            }

            .table-wrap {
                overflow: visible;
            }

            thead {
                display: table-header-group;
            }

            tr,
            .stat {
                break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" class="print-button" onclick="window.print()">
            طباعة التقرير
        </button>

        <button type="button" class="close-button" onclick="window.close()">
            إغلاق
        </button>
    </div>

    <main class="sheet">
        <header class="report-header">
            <div class="brand">
                <h1>{{ config('app.name') }}</h1>
                <p>نظام إدارة توزيع المواد الغذائية</p>
            </div>

            <div class="document-title">
                <h2>تقرير الأرباح التقريبية</h2>
                <p>فواتير البيع ومرتجعات البيع المعتمدة</p>
            </div>
        </header>

        <section class="section">
            <h3 class="section-title">ملخص النتائج</h3>

            <div class="stats">
                <div class="stat">
                    <span>عدد الحركات</span>
                    <strong>{{ number_format($totals['count']) }}</strong>
                </div>

                <div class="stat">
                    <span>فواتير البيع</span>
                    <strong>{{ number_format($totals['invoice_count']) }}</strong>
                </div>

                <div class="stat">
                    <span>مرتجعات البيع</span>
                    <strong>{{ number_format($totals['return_count']) }}</strong>
                </div>

                <div class="stat">
                    <span>صافي الكمية</span>
                    <strong>{{ $quantity($totals['quantity']) }}</strong>
                </div>

                <div class="stat">
                    <span>صافي المبيعات</span>
                    <strong class="{{ $totals['sales_amount'] < 0 ? 'negative' : 'positive' }}">
                        {{ $money($totals['sales_amount']) }}
                    </strong>
                </div>

                <div class="stat">
                    <span>صافي تكلفة البضاعة</span>
                    <strong>{{ $money($totals['cost_amount']) }}</strong>
                </div>

                <div class="stat">
                    <span>مجمل الربح</span>
                    <strong class="{{ $totals['profit_amount'] < 0 ? 'negative' : 'positive' }}">
                        {{ $money($totals['profit_amount']) }}
                    </strong>
                </div>

                <div class="stat">
                    <span>هامش الربح الإجمالي</span>
                    <strong class="{{ $totals['margin_percent'] < 0 ? 'negative' : 'positive' }}">
                        {{ $percent($totals['margin_percent']) }}
                    </strong>
                </div>
            </div>
        </section>

        @if ($filterSummary !== [])
            <section class="section">
                <h3 class="section-title">الفلاتر المطبقة</h3>

                <div class="filters">
                    @foreach ($filterSummary as $label => $value)
                        <div class="filter">
                            <b>{{ $label }}:</b>
                            {{ $value }}
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="section">
            <h3 class="section-title">تفاصيل الحركات</h3>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>نوع الحركة</th>
                            <th>رقم المستند</th>
                            <th>التاريخ</th>
                            <th>العميل</th>
                            <th>المستودع</th>
                            <th>السيارة</th>
                            <th>خط التوزيع</th>
                            <th>المندوب</th>
                            <th class="number">الكمية</th>
                            <th class="number">صافي المبيعات</th>
                            <th class="number">تكلفة البضاعة</th>
                            <th class="number">مجمل الربح</th>
                            <th class="number">هامش الربح</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($entries as $entry)
                            <tr class="{{ $entry->entry_type === 'return' ? 'return-row' : '' }}">
                                <td class="number">{{ $loop->iteration }}</td>
                                <td>
                                    <span class="badge {{ $entry->entry_type === 'return' ? 'badge-return' : 'badge-invoice' }}">
                                        {{ $entryTypeLabels[$entry->entry_type] ?? $entry->entry_type }}
                                    </span>
                                </td>
                                <td class="number">{{ $entry->document_number }}</td>
                                <td class="number">{{ $entry->entry_date?->format('Y-m-d') ?? '-' }}</td>
                                <td>{{ $entry->customer?->name ?? '-' }}</td>
                                <td>{{ $entry->warehouse?->name ?? '-' }}</td>
                                <td class="number">{{ $entry->vehicle?->plate_number ?? '-' }}</td>
                                <td>{{ $entry->route?->name ?? '-' }}</td>
                                <td>{{ $entry->salesRepresentative?->name ?? '-' }}</td>
                                <td class="number {{ (float) $entry->quantity < 0 ? 'negative' : '' }}">
                                    {{ $quantity($entry->quantity) }}
                                </td>
                                <td class="number {{ (float) $entry->sales_amount < 0 ? 'negative' : '' }}">
                                    {{ $money($entry->sales_amount) }}
                                </td>
                                <td class="number {{ (float) $entry->cost_amount < 0 ? 'negative' : '' }}">
                                    {{ $money($entry->cost_amount) }}
                                </td>
                                <td class="number {{ (float) $entry->profit_amount < 0 ? 'negative' : 'positive' }}">
                                    {{ $money($entry->profit_amount) }}
                                </td>
                                <td class="number {{ (float) $entry->margin_percent < 0 ? 'negative' : 'positive' }}">
                                    {{ $percent($entry->margin_percent) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="14" class="empty">
                                    لا توجد نتائج مطابقة للفلاتر المحددة.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>

                    <tfoot>
                        <tr>
                            <td colspan="9">الإجمالي</td>
                            <td class="number">{{ $quantity($totals['quantity']) }}</td>
                            <td class="number">{{ $money($totals['sales_amount']) }}</td>
                            <td class="number">{{ $money($totals['cost_amount']) }}</td>
                            <td class="number {{ $totals['profit_amount'] < 0 ? 'negative' : 'positive' }}">
                                {{ $money($totals['profit_amount']) }}
                            </td>
                            <td class="number {{ $totals['margin_percent'] < 0 ? 'negative' : 'positive' }}">
                                {{ $percent($totals['margin_percent']) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="note">
                التقرير تقريبي ويعتمد على تكلفة المخزون المحفوظة وقت اعتماد فاتورة البيع أو مرتجع البيع.
            </div>
        </section>

        <footer class="report-footer">
            <span>تاريخ الإنشاء: {{ now()->format('Y-m-d H:i') }}</span>
            <span>أُنشئ بواسطة: {{ $generatedBy ?: '-' }}</span>
        </footer>
    </main>
</body>
</html>
