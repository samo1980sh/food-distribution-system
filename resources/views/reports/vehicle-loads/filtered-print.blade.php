@php
    $money = fn ($value): string => number_format((float) $value, 2) . ' ل.س';
    $quantity = fn ($value): string => number_format((float) $value, 3);

    $statusClasses = [
        'draft' => 'status-draft',
        'approved' => 'status-approved',
        'cancelled' => 'status-cancelled',
        'closed' => 'status-closed',
    ];
@endphp

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>تقرير تحميلات السيارات</title>

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
            line-height: 1.55;
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
            padding: 26px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 12px 35px rgba(15, 35, 43, 0.1);
        }

        .report-header {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            padding-bottom: 15px;
            border-bottom: 3px solid #0f766e;
        }

        .brand h1,
        .document-title h2 {
            margin: 0;
        }

        .brand h1 {
            color: #0f766e;
            font-size: 24px;
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
            margin-top: 18px;
        }

        .section-title {
            margin: 0 0 10px;
            padding-right: 9px;
            border-right: 4px solid #0f766e;
            font-size: 15px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 9px;
        }

        .stat {
            padding: 10px 12px;
            border: 1px solid #dbe3e6;
            border-radius: 8px;
            background: #f8fbfb;
        }

        .stat span {
            display: block;
            color: #64767c;
            font-size: 10px;
        }

        .stat strong {
            display: block;
            margin-top: 3px;
            color: #0f5f58;
            font-size: 15px;
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
            padding: 6px 5px;
            border: 1px solid #cfdadd;
            vertical-align: middle;
        }

        th {
            background: #edf5f4;
            color: #24433f;
            font-size: 10px;
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

        .status-approved {
            background: #dcfce7;
            color: #166534;
        }

        .status-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-closed {
            background: #e5e7eb;
            color: #374151;
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
            margin-top: 20px;
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

            .brand h1 {
                font-size: 18px;
            }

            .document-title h2 {
                font-size: 14px;
            }

            .section {
                margin-top: 9px;
            }

            .section-title {
                margin-bottom: 6px;
                font-size: 10px;
            }

            .stats {
                gap: 5px;
            }

            .stat {
                padding: 5px 7px;
            }

            .stat span {
                font-size: 7px;
            }

            .stat strong {
                font-size: 9px;
            }

            .filter {
                padding: 2px 5px;
            }

            th,
            td {
                padding: 3px 2px;
            }

            th {
                font-size: 6.5px;
            }

            tr,
            .stat,
            .filter {
                break-inside: avoid;
            }

            .table-wrap {
                overflow: visible;
            }

            .report-footer {
                margin-top: 8px;
                font-size: 7px;
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
                <h2>تقرير تحميلات السيارات</h2>
                <p>النتائج المطابقة للفلاتر والبحث الحالي</p>
            </div>
        </header>

        <section class="section">
            <div class="stats">
                <div class="stat">
                    <span>عدد أوامر التحميل</span>
                    <strong>{{ number_format($totals['count']) }}</strong>
                </div>

                <div class="stat">
                    <span>إجمالي عدد المواد</span>
                    <strong>{{ number_format($totals['items_count']) }}</strong>
                </div>

                <div class="stat">
                    <span>إجمالي الكمية</span>
                    <strong>{{ $quantity($totals['total_quantity']) }}</strong>
                </div>

                <div class="stat">
                    <span>إجمالي التكلفة</span>
                    <strong>{{ $money($totals['total_cost']) }}</strong>
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
            <h3 class="section-title">أوامر التحميل</h3>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>رقم الأمر</th>
                            <th>التاريخ</th>
                            <th>السيارة</th>
                            <th>خط التوزيع</th>
                            <th>السائق</th>
                            <th>المندوب</th>
                            <th>المستودع المصدر</th>
                            <th>مستودع السيارة</th>
                            <th class="number">عدد المواد</th>
                            <th class="number">إجمالي الكمية</th>
                            <th class="number">إجمالي التكلفة</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($loads as $vehicleLoad)
                            <tr>
                                <td class="number">
                                    {{ $vehicleLoad->load_number }}
                                </td>

                                <td class="number">
                                    {{ $vehicleLoad->load_date?->format('Y-m-d') ?? '-' }}
                                </td>

                                <td class="number">
                                    {{ $vehicleLoad->vehicle?->plate_number ?? '-' }}
                                </td>

                                <td>{{ $vehicleLoad->route?->name ?? '-' }}</td>
                                <td>{{ $vehicleLoad->driver?->name ?? '-' }}</td>

                                <td>
                                    {{ $vehicleLoad->salesRepresentative?->name ?? '-' }}
                                </td>

                                <td>{{ $vehicleLoad->fromWarehouse?->name ?? '-' }}</td>
                                <td>{{ $vehicleLoad->toWarehouse?->name ?? '-' }}</td>

                                <td class="number">
                                    {{ number_format($vehicleLoad->items_count) }}
                                </td>

                                <td class="number">
                                    {{ $quantity($vehicleLoad->total_quantity) }}
                                </td>

                                <td class="number">
                                    {{ $money($vehicleLoad->total_cost) }}
                                </td>

                                <td>
                                    <span class="status {{ $statusClasses[$vehicleLoad->status] ?? '' }}">
                                        {{ $statusLabels[$vehicleLoad->status]
                                            ?? $vehicleLoad->status
                                        }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="empty">
                                    لا توجد أوامر تحميل مطابقة للفلاتر الحالية.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>

                    @if ($loads->isNotEmpty())
                        <tfoot>
                            <tr>
                                <td colspan="8">الإجمالي</td>

                                <td class="number">
                                    {{ number_format($totals['items_count']) }}
                                </td>

                                <td class="number">
                                    {{ $quantity($totals['total_quantity']) }}
                                </td>

                                <td class="number">
                                    {{ $money($totals['total_cost']) }}
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

            <span>FreshRoute — تقرير تحميلات السيارات</span>
        </footer>
    </main>
</body>
</html>
