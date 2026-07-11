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

    $vehicleStatusLabels = [
        'active' => 'فعالة',
        'maintenance' => 'قيد الصيانة',
        'out_of_service' => 'خارج الخدمة',
    ];
@endphp

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        مخزون السيارة {{ $vehicle->plate_number }}
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
            font-size: 11px;
            line-height: 1.55;
        }

        .toolbar,
        .sheet {
            width: min(1350px, calc(100% - 30px));
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

        .details-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 9px;
        }

        .detail-item,
        .stat {
            padding: 9px 11px;
            border: 1px solid #dbe3e6;
            border-radius: 8px;
        }

        .detail-item span,
        .stat span {
            display: block;
            color: #64767c;
            font-size: 10px;
        }

        .detail-item strong,
        .stat strong {
            display: block;
            margin-top: 3px;
            font-size: 13px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 9px;
            margin-top: 10px;
        }

        .stat {
            background: #f8fbfb;
        }

        .stat strong {
            color: #0f5f58;
            font-size: 15px;
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

        .expiry {
            display: inline-block;
            padding: 2px 7px;
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
            margin-top: 12px;
            padding: 8px 10px;
            border-right: 4px solid #d97706;
            background: #fffbeb;
            color: #784b0b;
        }

        .signatures {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 35px;
            margin-top: 45px;
        }

        .signature {
            min-height: 65px;
            padding-top: 8px;
            border-top: 1px solid #64767c;
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
                font-size: 8px;
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

            .details-grid,
            .stats {
                gap: 5px;
            }

            .detail-item,
            .stat {
                padding: 5px 7px;
            }

            .detail-item span,
            .stat span {
                font-size: 7px;
            }

            .detail-item strong,
            .stat strong {
                font-size: 9px;
            }

            th,
            td {
                padding: 3px;
            }

            th {
                font-size: 7px;
            }

            tr,
            .detail-item,
            .stat,
            .note {
                break-inside: avoid;
            }

            .table-wrap {
                overflow: visible;
            }

            .signatures {
                margin-top: 28px;
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
            طباعة مخزون السيارة
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
                <h2>كشف مخزون سيارة</h2>
                <p dir="ltr">{{ $vehicle->plate_number }}</p>
            </div>
        </header>

        <section class="section">
            <h3 class="section-title">بيانات السيارة والمستودع</h3>

            <div class="details-grid">
                <div class="detail-item">
                    <span>رقم السيارة</span>
                    <strong dir="ltr">{{ $vehicle->plate_number }}</strong>
                </div>

                <div class="detail-item">
                    <span>رمز السيارة</span>
                    <strong dir="ltr">{{ $vehicle->code }}</strong>
                </div>

                <div class="detail-item">
                    <span>اسم / وصف السيارة</span>
                    <strong>{{ $vehicle->name ?: '-' }}</strong>
                </div>

                <div class="detail-item">
                    <span>حالة السيارة</span>
                    <strong>
                        {{ $vehicleStatusLabels[$vehicle->status]
                            ?? $vehicle->status
                        }}
                    </strong>
                </div>

                <div class="detail-item">
                    <span>مستودع السيارة</span>
                    <strong>{{ $warehouse?->name ?? '-' }}</strong>
                </div>

                <div class="detail-item">
                    <span>رمز المستودع</span>
                    <strong dir="ltr">{{ $warehouse?->code ?? '-' }}</strong>
                </div>

                <div class="detail-item">
                    <span>سعة التحميل</span>
                    <strong>{{ $vehicle->capacity !== null ? $quantity($vehicle->capacity) : '-' }}</strong>
                </div>

                <div class="detail-item">
                    <span>آخر تحديث للأرصدة</span>
                    <strong dir="ltr">
                        {{ $balances->max('updated_at')?->format('Y-m-d H:i') ?? '-' }}
                    </strong>
                </div>
            </div>

            <div class="stats">
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

        <section class="section">
            <h3 class="section-title">تفاصيل المخزون الحالي</h3>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>SKU</th>
                            <th>المنتج</th>
                            <th>رقم التشغيلة</th>
                            <th>تاريخ الصلاحية</th>
                            <th>حالة الصلاحية</th>
                            <th class="number">الكمية</th>
                            <th class="number">سعر الشراء</th>
                            <th class="number">القيمة التقديرية</th>
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
                                <td class="number">{{ $loop->iteration }}</td>
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
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="empty">
                                    لا يوجد رصيد حالي مسجل داخل مستودع هذه السيارة.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>

                    @if ($balances->isNotEmpty())
                        <tfoot>
                            <tr>
                                <td colspan="6">الإجمالي</td>
                                <td class="number">{{ $quantity($totals['quantity']) }}</td>
                                <td></td>
                                <td class="number">{{ $money($totals['estimated_value']) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            <div class="note">
                القيمة التقديرية محسوبة من الكمية الحالية × سعر شراء المنتج، وليست تكلفة محاسبية نهائية للمخزون.
            </div>
        </section>

        <section class="signatures">
            <div class="signature">مسؤول السيارة</div>
            <div class="signature">أمين المستودع</div>
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

            <span>FreshRoute — كشف مخزون سيارة</span>
        </footer>
    </main>
</body>
</html>
