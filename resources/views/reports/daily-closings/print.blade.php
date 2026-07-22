@php
    $statusLabels = [
        'draft' => 'مسودة',
        'confirmed' => 'معتمد',
        'cancelled' => 'ملغي',
    ];

    $money = fn ($value): string => number_format((float) $value, 2) . ' ل.س';
    $quantity = fn ($value): string => number_format((float) $value, 3);

    $netSales = max(
        (float) $dailyClosing->total_sales_amount
        - (float) $dailyClosing->total_returns_amount,
        0,
    );
@endphp

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        طباعة الإغلاق {{ $dailyClosing->closing_number }}
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
            font-size: 13px;
            line-height: 1.7;
        }

        .print-toolbar {
            width: min(1120px, calc(100% - 32px));
            margin: 20px auto 0;
            display: flex;
            justify-content: flex-start;
            gap: 10px;
        }

        .print-toolbar button {
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
            width: min(1120px, calc(100% - 32px));
            margin: 16px auto 40px;
            background: #fff;
            padding: 34px;
            border-radius: 12px;
            box-shadow: 0 12px 35px rgba(15, 35, 43, 0.1);
        }

        .report-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 24px;
            padding-bottom: 22px;
            border-bottom: 3px solid #0f766e;
        }

        .brand h1 {
            margin: 0;
            font-size: 26px;
            color: #0f766e;
        }

        .brand p {
            margin: 2px 0 0;
            color: #607278;
        }

        .report-title {
            text-align: left;
        }

        .report-title h2 {
            margin: 0;
            font-size: 22px;
        }

        .report-title p {
            margin: 4px 0 0;
            color: #607278;
        }

        .status {
            display: inline-flex;
            margin-top: 8px;
            padding: 3px 12px;
            border-radius: 999px;
            font-weight: 700;
        }

        .status-draft {
            background: #fef3c7;
            color: #92400e;
        }

        .status-confirmed {
            background: #dcfce7;
            color: #166534;
        }

        .status-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .section {
            margin-top: 26px;
        }

        .section-title {
            margin: 0 0 12px;
            padding-right: 10px;
            border-right: 4px solid #0f766e;
            font-size: 17px;
        }

        .meta-grid,
        .summary-grid {
            display: grid;
            gap: 12px;
        }

        .meta-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .summary-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .meta-item,
        .summary-card {
            border: 1px solid #dbe3e6;
            border-radius: 8px;
            padding: 11px 13px;
        }

        .meta-item span,
        .summary-card span {
            display: block;
            color: #64767c;
            font-size: 12px;
        }

        .meta-item strong,
        .summary-card strong {
            display: block;
            margin-top: 2px;
            font-size: 14px;
        }

        .summary-card strong {
            font-size: 16px;
            direction: ltr;
            text-align: right;
        }

        .summary-card.difference {
            border-color: #f59e0b;
            background: #fffaf0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 9px 8px;
            border: 1px solid #cfdadd;
            vertical-align: middle;
        }

        th {
            background: #edf5f4;
            color: #24433f;
            font-weight: 700;
        }

        td.number,
        th.number {
            direction: ltr;
            text-align: center;
            white-space: nowrap;
        }

        .notes {
            min-height: 64px;
            padding: 13px;
            border: 1px solid #dbe3e6;
            border-radius: 8px;
            white-space: pre-wrap;
        }

        .signatures {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 24px;
            margin-top: 48px;
        }

        .signature {
            min-height: 90px;
            padding-top: 8px;
            border-top: 1px solid #64767c;
            text-align: center;
        }

        .report-footer {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-top: 32px;
            padding-top: 12px;
            border-top: 1px solid #dbe3e6;
            color: #718187;
            font-size: 11px;
        }

        @page {
            size: A4 landscape;
            margin: 10mm;
        }

        @media print {
            body {
                background: #fff;
                font-size: 10px;
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
                padding-bottom: 12px;
            }

            .section {
                margin-top: 14px;
            }

            .meta-item,
            .summary-card {
                padding: 7px 9px;
            }

            th,
            td {
                padding: 5px;
            }

            tr,
            .summary-card,
            .meta-item {
                break-inside: avoid;
            }

            .signatures {
                margin-top: 34px;
            }
        }

        @media (max-width: 800px) {
            .report-header {
                display: block;
            }

            .report-title {
                margin-top: 15px;
                text-align: right;
            }

            .meta-grid,
            .summary-grid,
            .signatures {
                grid-template-columns: 1fr;
            }

            .sheet {
                padding: 18px;
            }
        }
    </style>
</head>

<body>
    <div class="print-toolbar no-print">
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

            <div class="report-title">
                <h2>تقرير الإغلاق اليومي</h2>

                <p>
                    رقم الإغلاق:
                    <strong>{{ $dailyClosing->closing_number }}</strong>
                </p>

                <span class="status status-{{ $dailyClosing->status }}">
                    {{ $statusLabels[$dailyClosing->status] ?? $dailyClosing->status }}
                </span>
            </div>
        </header>

        <section class="section">
            <h3 class="section-title">بيانات الإغلاق</h3>

            <div class="meta-grid">
                <div class="meta-item">
                    <span>تاريخ الإغلاق</span>
                    <strong>{{ $dailyClosing->closing_date?->format('Y-m-d') ?? '-' }}</strong>
                </div>

                <div class="meta-item">
                    <span>المستودع</span>
                    <strong>{{ $dailyClosing->warehouse?->name ?? '-' }}</strong>
                </div>

                <div class="meta-item">
                    <span>السيارة</span>
                    <strong>{{ $dailyClosing->vehicle?->plate_number ?? '-' }}</strong>
                </div>

                <div class="meta-item">
                    <span>خط التوزيع</span>
                    <strong>{{ $dailyClosing->route?->name ?? '-' }}</strong>
                </div>

                <div class="meta-item">
                    <span>السائق</span>
                    <strong>{{ $dailyClosing->driver?->name ?? '-' }}</strong>
                </div>

                <div class="meta-item">
                    <span>مندوب المبيعات</span>
                    <strong>{{ $dailyClosing->salesRepresentative?->name ?? '-' }}</strong>
                </div>

                <div class="meta-item">
                    <span>مسار الإغلاق</span>
                    <strong>{{ $dailyClosing->field_workflow ? 'تسليم ميداني' : 'إغلاق إداري' }}</strong>
                </div>

                <div class="meta-item">
                    <span>تاريخ الاعتماد</span>
                    <strong>{{ $dailyClosing->confirmed_at?->format('Y-m-d H:i') ?? '-' }}</strong>
                </div>

                <div class="meta-item">
                    <span>تاريخ تثبيت لقطة الإغلاق</span>
                    <strong>{{ $dailyClosing->snapshot_at?->format('Y-m-d H:i') ?? '-' }}</strong>
                </div>
            </div>
        </section>

        <section class="section">
            <h3 class="section-title">الملخص التشغيلي</h3>

            <div class="summary-grid">
                <div class="summary-card">
                    <span>رصيد بداية اليوم</span>
                    <strong>{{ $quantity($dailyClosing->total_opening_quantity) }}</strong>
                </div>

                <div class="summary-card">
                    <span>إجمالي الوارد الدفتري</span>
                    <strong>{{ $quantity($dailyClosing->total_movement_in_quantity) }}</strong>
                </div>

                <div class="summary-card">
                    <span>إجمالي الصادر الدفتري</span>
                    <strong>{{ $quantity($dailyClosing->total_movement_out_quantity) }}</strong>
                </div>

                <div class="summary-card">
                    <span>الرصيد الدفتري المتوقع</span>
                    <strong>{{ $quantity($dailyClosing->total_expected_quantity) }}</strong>
                </div>

                <div class="summary-card">
                    <span>إجمالي الكمية المحمّلة</span>
                    <strong>{{ $quantity($dailyClosing->total_loaded_quantity) }}</strong>
                </div>

                <div class="summary-card">
                    <span>إجمالي الكمية المباعة</span>
                    <strong>{{ $quantity($dailyClosing->total_sold_quantity) }}</strong>
                </div>

                <div class="summary-card">
                    <span>إجمالي الكمية المرتجعة</span>
                    <strong>{{ $quantity($dailyClosing->total_returned_quantity) }}</strong>
                </div>

                <div class="summary-card">
                    <span>عدد المواد</span>
                    <strong>{{ $dailyClosing->items->count() }}</strong>
                </div>
            </div>
        </section>

        <section class="section">
            <h3 class="section-title">الملخص المالي</h3>

            <div class="summary-grid">
                <div class="summary-card">
                    <span>إجمالي المبيعات</span>
                    <strong>{{ $money($dailyClosing->total_sales_amount) }}</strong>
                </div>

                <div class="summary-card">
                    <span>إجمالي المرتجعات</span>
                    <strong>{{ $money($dailyClosing->total_returns_amount) }}</strong>
                </div>

                <div class="summary-card">
                    <span>صافي المبيعات</span>
                    <strong>{{ $money($netSales) }}</strong>
                </div>

                <div class="summary-card">
                    <span>إجمالي التحصيلات</span>
                    <strong>{{ $money($dailyClosing->total_collections_amount) }}</strong>
                </div>

                <div class="summary-card">
                    <span>نقد الفواتير</span>
                    <strong>{{ $money($dailyClosing->invoice_cash_amount) }}</strong>
                </div>

                <div class="summary-card">
                    <span>التحصيل النقدي</span>
                    <strong>{{ $money($dailyClosing->cash_collections_amount) }}</strong>
                </div>

                <div class="summary-card">
                    <span>التحصيل غير النقدي</span>
                    <strong>{{ $money($dailyClosing->non_cash_collections_amount) }}</strong>
                </div>

                <div class="summary-card">
                    <span>مصاريف السيارات</span>
                    <strong>{{ $money($dailyClosing->total_vehicle_expenses_amount) }}</strong>
                </div>

                <div class="summary-card">
                    <span>المصاريف النقدية</span>
                    <strong>{{ $money($dailyClosing->cash_vehicle_expenses_amount) }}</strong>
                </div>

                <div class="summary-card">
                    <span>المصاريف غير النقدية</span>
                    <strong>{{ $money($dailyClosing->non_cash_vehicle_expenses_amount) }}</strong>
                </div>

                <div class="summary-card">
                    <span>النقد المتوقع</span>
                    <strong>{{ $money($dailyClosing->expected_cash_amount) }}</strong>
                </div>

                <div class="summary-card">
                    <span>النقد الفعلي</span>
                    <strong>{{ $money($dailyClosing->actual_cash_amount) }}</strong>
                </div>

                <div class="summary-card difference">
                    <span>فرق الصندوق</span>
                    <strong>{{ $money($dailyClosing->cash_difference) }}</strong>
                </div>
            </div>

            @if ($dailyClosing->field_workflow)
                <div class="meta-grid" style="margin-top: 14px;">
                    <div class="meta-item">
                        <span>تسليم جرد السيارة</span>
                        <strong>{{ $dailyClosing->inventory_submitted_at?->format('Y-m-d H:i') ?? 'بانتظار السائق' }}</strong>
                    </div>
                    <div class="meta-item">
                        <span>سلّم الجرد</span>
                        <strong>{{ $dailyClosing->inventorySubmitter?->name ?? '-' }}</strong>
                    </div>
                    <div class="meta-item">
                        <span>تسليم النقد</span>
                        <strong>{{ $dailyClosing->cash_submitted_at?->format('Y-m-d H:i') ?? 'بانتظار المندوب' }}</strong>
                    </div>
                    <div class="meta-item">
                        <span>سلّم النقد</span>
                        <strong>{{ $dailyClosing->cashSubmitter?->name ?? '-' }}</strong>
                    </div>
                    <div class="meta-item" style="grid-column: 1 / -1;">
                        <span>تفسير فرق الصندوق</span>
                        <strong>{{ $dailyClosing->cash_notes ?: '-' }}</strong>
                    </div>
                </div>
            @endif
        </section>

        <section class="section">
            <h3 class="section-title">تفصيل التحصيلات</h3>

            <table>
                <thead>
                    <tr>
                        <th>نقدي</th>
                        <th>تحويل مصرفي</th>
                        <th>شيكات</th>
                        <th>أخرى</th>
                        <th>غير نقدي</th>
                        <th>الإجمالي</th>
                    </tr>
                </thead>

                <tbody>
                    <tr>
                        <td class="number">{{ $money($dailyClosing->cash_collections_amount) }}</td>
                        <td class="number">{{ $money($dailyClosing->bank_transfer_collections_amount) }}</td>
                        <td class="number">{{ $money($dailyClosing->cheque_collections_amount) }}</td>
                        <td class="number">{{ $money($dailyClosing->other_collections_amount) }}</td>
                        <td class="number">{{ $money($dailyClosing->non_cash_collections_amount) }}</td>
                        <td class="number">{{ $money($dailyClosing->total_collections_amount) }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="section">
            <h3 class="section-title">ملخص المواد</h3>

            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>SKU</th>
                        <th>المنتج</th>
                        <th class="number">بداية اليوم</th>
                        <th class="number">الوارد</th>
                        <th class="number">الصادر</th>
                        <th class="number">المحمّل</th>
                        <th class="number">المباع</th>
                        <th class="number">المرتجع</th>
                        <th class="number">المتوقع الدفتري</th>
                        <th class="number">الفعلي</th>
                        <th class="number">الفرق</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($dailyClosing->items as $item)
                        <tr>
                            <td class="number">{{ $loop->iteration }}</td>
                            <td class="number">{{ $item->product?->sku ?? '-' }}</td>
                            <td>{{ $item->product?->name_ar ?? '-' }}</td>
                            <td class="number">{{ $quantity($item->opening_quantity) }}</td>
                            <td class="number">{{ $quantity($item->movement_in_quantity) }}</td>
                            <td class="number">{{ $quantity($item->movement_out_quantity) }}</td>
                            <td class="number">{{ $quantity($item->loaded_quantity) }}</td>
                            <td class="number">{{ $quantity($item->sold_quantity) }}</td>
                            <td class="number">{{ $quantity($item->returned_quantity) }}</td>
                            <td class="number">{{ $quantity($item->expected_quantity) }}</td>
                            <td class="number">
                                {{ $item->actual_quantity === null ? '-' : $quantity($item->actual_quantity) }}
                            </td>
                            <td class="number">{{ $quantity($item->difference_quantity) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" style="text-align: center;">
                                لا توجد مواد ضمن هذا الإغلاق.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        @if (filled($dailyClosing->notes))
            <section class="section">
                <h3 class="section-title">الملاحظات</h3>

                <div class="notes">{{ $dailyClosing->notes }}</div>
            </section>
        @endif

        <section class="signatures">
            <div class="signature">
                توقيع مندوب المبيعات
            </div>

            <div class="signature">
                توقيع المحاسب
            </div>

            <div class="signature">
                توقيع المشرف
            </div>
        </section>

        <footer class="report-footer">
            <span>
                تم إنشاء التقرير:
                {{ now()->format('Y-m-d H:i') }}
            </span>

            <span>
                FreshRoute — تقرير الإغلاق اليومي
            </span>
        </footer>
    </main>
</body>
</html>