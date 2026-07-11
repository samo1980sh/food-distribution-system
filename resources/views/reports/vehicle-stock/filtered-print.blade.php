@php
    $money = fn ($value): string => number_format((float) $value, 2) . ' ل.س';
    $quantity = fn ($value): string => number_format((float) $value, 3);

    $expiryInfo = function ($date): array {
        if (! $date) {
            return ['بدون تاريخ', 'expiry-none'];
        }

        if ($date->isBefore(today())) {
            return ['منتهي', 'expiry-expired'];
        }

        if ($date->isSameDay(today()) || $date->isBefore(today()->addDays(30))) {
            return ['قريب الانتهاء', 'expiry-near'];
        }

        return ['صالح', 'expiry-valid'];
    };
@endphp

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>تقرير مخزون السيارات</title>

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
            grid-template-columns: repeat(5, minmax(0, 1fr));
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
            font-size: 9px;
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

        tfoot td {
            background: #edf5f4;
            font-weight: 700;
        }

        .expiry {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 999px;
            font-weight: 700;
            white-space: nowrap;
        }

        .expiry-expired {
            background: #fee2e2;
            color: #991b1b;
        }

        .expiry-near {
            background: #fef3c7;
            color: #92400e;
        }

        .expiry-valid {
            background: #dcfce7;
            color: #166534;
        }

        .expiry-none {
            background: #e5e7eb;
            color: #374151;
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
            margin: 7mm;
        }

        @media print {
            body {
                background: #fff;
                font-size: 6.5px;
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
                padding-bottom: 7px;
            }

            .brand h1 {
                font-size: 17px;
            }

            .document-title h2 {
                font-size: 13px;
            }

            .section {
                margin-top: 8px;
            }

            .section-title {
                margin-bottom: 5px;
                font-size: 9px;
            }

            .stats {
                gap: 4px;
            }

            .stat {
                padding: 4px 6px;
            }

            .stat span {
                font-size: 6px;
            }

            .stat strong {
                font-size: 8px;
            }

            .filter {
                padding: 2px 5px;
            }

            th,
            td {
                padding: 2.5px 2px;
            }

            th {
                font-size: 6px;
            }

            tr,
            .stat,
            .filter,
            .note {
                break-inside: avoid;
            }

            .table-wrap {
                overflow: visible;
            }

            .report-footer {
                margin-top: 7px;
                font-size: 6px;
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
            طباعة التقرير
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

            <div class="document-title">
                <h2>تقرير مخزون السيارات</h2>
                <p>الأرصدة الحالية المطابقة للفلاتر والبحث</p>
            </div>
        </header>

        <section class="section">
            <div class="stats">
                <div class="stat">
                    <span>عدد السيارات</span>
                    <strong>{{ number_format($totals['vehicles_count']) }}</strong>
                </div>

                <div class="stat">
                    <span>عدد المنتجات</span>
                    <strong>{{ number_format($totals['products_count']) }}</strong>
                </div>

                <div class="stat">
                    <span>عدد الأرصدة والتشغيلات</span>
                    <strong>{{ number_format($totals['rows_count']) }}</strong>
                </div>

                <div class="stat">
                    <span>إجمالي الكمية</span>
                    <strong>{{ $quantity($totals['quantity']) }}</strong>
                </div>

                <div class="stat">
                    <span>القيمة التقديرية</span>
                    <strong>{{ $money($totals['estimated_value']) }}</strong>
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
            <h3 class="section-title">تفاصيل مخزون السيارات</h3>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>السيارة</th>
                            <th>مستودع السيارة</th>
                            <th>SKU</th>
                            <th>المنتج</th>
                            <th>التشغيلة</th>
                            <th>الصلاحية</th>
                            <th>حالة الصلاحية</th>
                            <th class="number">الكمية</th>
                            <th class="number">سعر الشراء</th>
                            <th class="number">القيمة التقديرية</th>
                            <th>آخر تحديث</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($balances as $balance)
                            @php
                                [$expiryLabel, $expiryClass] = $expiryInfo($balance->expiry_date);
                                $estimatedValue = (float) $balance->quantity
                                    * (float) ($balance->product?->purchase_price ?? 0);
                            @endphp

                            <tr>
                                <td class="number">
                                    {{ $balance->warehouse?->vehicle?->plate_number ?? '-' }}
                                </td>

                                <td>{{ $balance->warehouse?->name ?? '-' }}</td>
                                <td class="number">{{ $balance->product?->sku ?? '-' }}</td>
                                <td>{{ $balance->product?->name_ar ?? '-' }}</td>
                                <td class="number">{{ $balance->batch_number ?: '-' }}</td>

                                <td class="number">
                                    {{ $balance->expiry_date?->format('Y-m-d') ?? '-' }}
                                </td>

                                <td>
                                    <span class="expiry {{ $expiryClass }}">
                                        {{ $expiryLabel }}
                                    </span>
                                </td>

                                <td class="number">{{ $quantity($balance->quantity) }}</td>

                                <td class="number">
                                    {{ $money($balance->product?->purchase_price ?? 0) }}
                                </td>

                                <td class="number">{{ $money($estimatedValue) }}</td>

                                <td class="number">
                                    {{ $balance->updated_at?->format('Y-m-d H:i') ?? '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="empty">
                                    لا توجد أرصدة مخزون سيارات مطابقة للفلاتر الحالية.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>

                    @if ($balances->isNotEmpty())
                        <tfoot>
                            <tr>
                                <td colspan="7">الإجمالي</td>
                                <td class="number">{{ $quantity($totals['quantity']) }}</td>
                                <td></td>
                                <td class="number">{{ $money($totals['estimated_value']) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            <div class="note">
                القيمة التقديرية محسوبة من الكمية الحالية × سعر شراء المنتج، وليست تكلفة محاسبية نهائية للمخزون.
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

            <span>FreshRoute — تقرير مخزون السيارات</span>
        </footer>
    </main>
</body>
</html>
