@php
    $money = fn ($value): string => number_format((float) $value, 2) . ' ل.س';
    $quantity = fn ($value): string => number_format((float) $value, 3);

    $statusLabels = [
        'draft' => 'مسودة',
        'approved' => 'معتمد',
        'cancelled' => 'ملغي',
        'closed' => 'مغلق',
    ];

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

    <title>
        أمر تحميل سيارة {{ $vehicleLoad->load_number }}
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
            width: min(1100px, calc(100% - 30px));
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
            padding-bottom: 17px;
            border-bottom: 3px solid #0f766e;
        }

        .brand h1,
        .document-title h2 {
            margin: 0;
        }

        .brand h1 {
            color: #0f766e;
            font-size: 25px;
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
            margin-top: 20px;
        }

        .section-title {
            margin: 0 0 11px;
            padding-right: 9px;
            border-right: 4px solid #0f766e;
            font-size: 16px;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 9px;
        }

        .detail-item {
            padding: 9px 11px;
            border: 1px solid #dbe3e6;
            border-radius: 8px;
        }

        .detail-item span {
            display: block;
            color: #64767c;
            font-size: 11px;
        }

        .detail-item strong {
            display: block;
            margin-top: 3px;
            font-size: 13px;
        }

        .status {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 999px;
            font-weight: 700;
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

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 8px 7px;
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

        tfoot td {
            background: #edf5f4;
            font-weight: 700;
        }

        .summary {
            width: min(430px, 100%);
            margin-top: 16px;
            margin-right: auto;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding: 8px 11px;
            border: 1px solid #dbe3e6;
            border-bottom: 0;
        }

        .summary-row:first-child {
            border-radius: 8px 8px 0 0;
        }

        .summary-row:last-child {
            border-bottom: 1px solid #dbe3e6;
            border-radius: 0 0 8px 8px;
        }

        .summary-row strong {
            direction: ltr;
            white-space: nowrap;
        }

        .summary-row.total {
            background: #edf5f4;
            color: #0f5f58;
            font-size: 14px;
        }

        .notes-box {
            min-height: 60px;
            padding: 11px;
            border: 1px solid #dbe3e6;
            border-radius: 8px;
            white-space: pre-wrap;
        }

        .signatures {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 35px;
            margin-top: 50px;
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
            margin-top: 25px;
            padding-top: 10px;
            border-top: 1px solid #dbe3e6;
            color: #718187;
            font-size: 10px;
        }

        @page {
            size: A4 portrait;
            margin: 9mm;
        }

        @media print {
            body {
                background: #fff;
                font-size: 9px;
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
                margin-top: 11px;
            }

            .details-grid {
                gap: 5px;
            }

            .detail-item {
                padding: 5px 6px;
            }

            .detail-item span {
                font-size: 8px;
            }

            .detail-item strong {
                font-size: 9px;
            }

            th,
            td {
                padding: 4px;
            }

            tr,
            .detail-item,
            .summary-row {
                break-inside: avoid;
            }

            .signatures {
                margin-top: 32px;
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
            طباعة أمر التحميل
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
                <h2>أمر تحميل سيارة</h2>

                <p dir="ltr">
                    {{ $vehicleLoad->load_number }}
                </p>
            </div>
        </header>

        <section class="section">
            <h3 class="section-title">بيانات أمر التحميل</h3>

            <div class="details-grid">
                <div class="detail-item">
                    <span>رقم أمر التحميل</span>
                    <strong dir="ltr">{{ $vehicleLoad->load_number }}</strong>
                </div>

                <div class="detail-item">
                    <span>تاريخ التحميل</span>
                    <strong dir="ltr">
                        {{ $vehicleLoad->load_date?->format('Y-m-d') ?? '-' }}
                    </strong>
                </div>

                <div class="detail-item">
                    <span>الحالة</span>

                    <strong>
                        <span class="status {{ $statusClasses[$vehicleLoad->status] ?? '' }}">
                            {{ $statusLabels[$vehicleLoad->status]
                                ?? $vehicleLoad->status
                            }}
                        </span>
                    </strong>
                </div>

                <div class="detail-item">
                    <span>السيارة</span>
                    <strong dir="ltr">
                        {{ $vehicleLoad->vehicle?->plate_number ?? '-' }}
                    </strong>
                </div>

                <div class="detail-item">
                    <span>خط التوزيع</span>
                    <strong>{{ $vehicleLoad->route?->name ?? '-' }}</strong>
                </div>

                <div class="detail-item">
                    <span>السائق</span>
                    <strong>{{ $vehicleLoad->driver?->name ?? '-' }}</strong>
                </div>

                <div class="detail-item">
                    <span>مندوب المبيعات</span>
                    <strong>
                        {{ $vehicleLoad->salesRepresentative?->name ?? '-' }}
                    </strong>
                </div>

                <div class="detail-item">
                    <span>المستودع المصدر</span>
                    <strong>{{ $vehicleLoad->fromWarehouse?->name ?? '-' }}</strong>
                </div>

                <div class="detail-item">
                    <span>مستودع السيارة</span>
                    <strong>{{ $vehicleLoad->toWarehouse?->name ?? '-' }}</strong>
                </div>

                <div class="detail-item">
                    <span>تاريخ الاعتماد</span>
                    <strong dir="ltr">
                        {{ $vehicleLoad->approved_at?->format('Y-m-d H:i') ?? '-' }}
                    </strong>
                </div>
            </div>
        </section>

        <section class="section">
            <h3 class="section-title">مواد التحميل</h3>

            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>المادة</th>
                        <th>رقم التشغيلة</th>
                        <th>تاريخ الصلاحية</th>
                        <th class="number">الكمية</th>
                        <th class="number">تكلفة الوحدة</th>
                        <th class="number">إجمالي التكلفة</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($vehicleLoad->items as $item)
                        <tr>
                            <td class="number">{{ $loop->iteration }}</td>

                            <td>
                                {{ $item->product?->name_ar ?? '-' }}
                            </td>

                            <td class="number">
                                {{ $item->batch_number ?: '-' }}
                            </td>

                            <td class="number">
                                {{ $item->expiry_date?->format('Y-m-d') ?? '-' }}
                            </td>

                            <td class="number">
                                {{ $quantity($item->quantity) }}
                            </td>

                            <td class="number">
                                {{ $money($item->unit_cost) }}
                            </td>

                            <td class="number">
                                {{ $money($item->total_cost) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 25px;">
                                لا توجد مواد مسجلة ضمن أمر التحميل هذا.
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                @if ($vehicleLoad->items->isNotEmpty())
                    <tfoot>
                        <tr>
                            <td colspan="4">إجمالي أمر التحميل</td>

                            <td class="number">
                                {{ $quantity($vehicleLoad->total_quantity) }}
                            </td>

                            <td></td>

                            <td class="number">
                                {{ $money($vehicleLoad->total_cost) }}
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>

            <div class="summary">
                <div class="summary-row">
                    <span>إجمالي الكمية</span>
                    <strong>{{ $quantity($vehicleLoad->total_quantity) }}</strong>
                </div>

                <div class="summary-row total">
                    <span>إجمالي التكلفة</span>
                    <strong>{{ $money($vehicleLoad->total_cost) }}</strong>
                </div>
            </div>
        </section>

        @if (filled($vehicleLoad->notes))
            <section class="section">
                <h3 class="section-title">الملاحظات</h3>

                <div class="notes-box">
                    {{ $vehicleLoad->notes }}
                </div>
            </section>
        @endif

        <section class="signatures">
            <div class="signature">توقيع أمين المستودع</div>
            <div class="signature">توقيع السائق</div>
            <div class="signature">اعتماد الإدارة</div>
        </section>

        <footer class="report-footer">
            <span>
                أُنشئ بواسطة:
                {{ $createdBy ?? '-' }}
            </span>

            <span>
                اعتمد بواسطة:
                {{ $approvedBy ?? '-' }}

                @if ($vehicleLoad->approved_at)
                    —
                    <span dir="ltr">
                        {{ $vehicleLoad->approved_at->format('Y-m-d H:i') }}
                    </span>
                @endif
            </span>

            <span>
                تاريخ الطباعة:
                {{ now()->format('Y-m-d H:i') }}
            </span>
        </footer>
    </main>
</body>
</html>
