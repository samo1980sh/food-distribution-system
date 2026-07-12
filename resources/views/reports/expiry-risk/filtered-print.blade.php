@php
    use App\Filament\Resources\ExpiryRiskReports\Tables\ExpiryRiskReportsTable;

    $money = fn ($value): string => number_format((float) $value, 2) . ' ل.س';
    $quantity = fn ($value): string => number_format((float) $value, 3);
@endphp

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقرير المواد القريبة من الانتهاء</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; background: #eef2f4; color: #17252a;
            font-family: Tahoma, Arial, sans-serif; font-size: 9px; line-height: 1.45;
        }
        .toolbar, .sheet {
            width: min(1700px, calc(100% - 30px));
            margin-right: auto; margin-left: auto;
        }
        .toolbar { display: flex; gap: 10px; margin-top: 18px; }
        .toolbar button {
            border: 0; border-radius: 8px; padding: 10px 22px;
            font: inherit; font-weight: 700; cursor: pointer;
        }
        .print-button { background: #0f766e; color: #fff; }
        .close-button { background: #dce4e7; color: #26363b; }
        .sheet {
            margin-top: 14px; margin-bottom: 35px; padding: 22px;
            background: #fff; border-radius: 12px;
            box-shadow: 0 12px 35px rgba(15, 35, 43, 0.1);
        }
        .report-header {
            display: flex; justify-content: space-between; gap: 24px;
            padding-bottom: 14px; border-bottom: 3px solid #0f766e;
        }
        .brand h1, .document-title h2 { margin: 0; }
        .brand h1 { color: #0f766e; font-size: 22px; }
        .brand p, .document-title p { margin: 3px 0 0; color: #607278; }
        .document-title { text-align: left; }
        .section { margin-top: 15px; }
        .section-title {
            margin: 0 0 8px; padding-right: 8px;
            border-right: 4px solid #0f766e; font-size: 13px;
        }
        .stats {
            display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 7px;
        }
        .stat {
            padding: 8px 9px; border: 1px solid #dbe3e6;
            border-radius: 8px; background: #f8fbfb;
        }
        .stat span { display: block; color: #64767c; font-size: 8px; }
        .stat strong {
            display: block; margin-top: 3px; color: #0f5f58;
            font-size: 12px; direction: ltr; text-align: right;
        }
        .danger-stat { background: #fef2f2; border-color: #fecaca; }
        .danger-stat strong { color: #b91c1c; }
        .warning-stat { background: #fffbeb; border-color: #fde68a; }
        .warning-stat strong { color: #b45309; }
        .filters { display: flex; flex-wrap: wrap; gap: 7px; }
        .filter {
            padding: 5px 9px; border: 1px solid #cfe1df;
            border-radius: 999px; background: #f1f8f7;
        }
        .filter b { color: #0f5f58; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td {
            padding: 5px 4px; border: 1px solid #cfdadd; vertical-align: middle;
        }
        th {
            background: #edf5f4; color: #24433f;
            font-size: 7.5px; font-weight: 700; white-space: nowrap;
        }
        td { font-size: 7.5px; }
        td.number, th.number {
            direction: ltr; text-align: center; white-space: nowrap;
        }
        tbody tr:nth-child(even) { background: #fafcfc; }
        tbody tr.missing { background: #fef2f2; }
        tbody tr.expired { background: #fff1f2; }
        tbody tr.critical { background: #fffbeb; }
        tfoot td { background: #edf5f4; font-weight: 700; }
        .badge {
            display: inline-block; min-width: 85px; padding: 3px 6px;
            border-radius: 999px; font-weight: 700; text-align: center; white-space: nowrap;
        }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-gray { background: #e5e7eb; color: #374151; }
        .badge-success { background: #dcfce7; color: #166534; }
        .empty { padding: 28px; color: #64767c; text-align: center; }
        .report-footer {
            display: flex; justify-content: space-between; gap: 20px;
            margin-top: 18px; padding-top: 9px; border-top: 1px solid #dbe3e6;
            color: #718187; font-size: 8px;
        }
        @media print {
            @page { size: A4 landscape; margin: 6mm; }
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet {
                width: 100%; margin: 0; padding: 0;
                border-radius: 0; box-shadow: none;
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
                <h2>تقرير المواد القريبة من الانتهاء</h2>
                <p>جميع المستودعات ومخزون السيارات</p>
            </div>
        </header>

        <section class="section">
            <h3 class="section-title">الإجماليات العامة</h3>
            <div class="stats">
                <div class="stat"><span>عدد الأرصدة</span><strong>{{ number_format($totals['rows_count']) }}</strong></div>
                <div class="stat"><span>عدد المنتجات</span><strong>{{ number_format($totals['products_count']) }}</strong></div>
                <div class="stat"><span>عدد المستودعات</span><strong>{{ number_format($totals['warehouses_count']) }}</strong></div>
                <div class="stat"><span>إجمالي الكمية</span><strong>{{ $quantity($totals['quantity']) }}</strong></div>
                <div class="stat"><span>إجمالي القيمة</span><strong>{{ $money($totals['inventory_value']) }}</strong></div>
            </div>
        </section>

        <section class="section">
            <h3 class="section-title">مؤشرات الخطر</h3>
            <div class="stats">
                <div class="stat danger-stat"><span>عدد التواريخ المفقودة</span><strong>{{ number_format($totals['missing_count']) }}</strong></div>
                <div class="stat danger-stat"><span>كمية التواريخ المفقودة</span><strong>{{ $quantity($totals['missing_quantity']) }}</strong></div>
                <div class="stat danger-stat"><span>قيمة المنتهي</span><strong>{{ $money($totals['expired_value']) }}</strong></div>
                <div class="stat warning-stat"><span>كمية القريب خلال 30 يومًا</span><strong>{{ $quantity($totals['near_quantity']) }}</strong></div>
                <div class="stat warning-stat"><span>قيمة القريب خلال 30 يومًا</span><strong>{{ $money($totals['near_value']) }}</strong></div>
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
            <h3 class="section-title">تفاصيل الأرصدة</h3>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>المستودع</th>
                            <th>النوع</th>
                            <th>السيارة</th>
                            <th>SKU</th>
                            <th>المنتج</th>
                            <th>التصنيف</th>
                            <th>الوحدة</th>
                            <th>التشغيلة</th>
                            <th>الصلاحية</th>
                            <th>الحالة</th>
                            <th>الأيام</th>
                            <th class="number">الكمية</th>
                            <th class="number">متوسط التكلفة</th>
                            <th class="number">القيمة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($balances as $balance)
                            @php
                                $status = ExpiryRiskReportsTable::expiryStatus($balance->expiry_date);
                                $days = ExpiryRiskReportsTable::daysRemaining($balance->expiry_date);
                                $rowClass = match ($status) {
                                    'missing' => 'missing',
                                    'expired' => 'expired',
                                    'today', 'critical_7' => 'critical',
                                    default => '',
                                };
                                $badgeClass = match (ExpiryRiskReportsTable::expiryStatusColor($status)) {
                                    'danger' => 'badge-danger',
                                    'warning' => 'badge-warning',
                                    'info' => 'badge-info',
                                    'success' => 'badge-success',
                                    default => 'badge-gray',
                                };
                            @endphp
                            <tr class="{{ $rowClass }}">
                                <td>{{ $balance->warehouse?->name ?? '-' }}</td>
                                <td>{{ ExpiryRiskReportsTable::warehouseTypeLabel($balance->warehouse?->type) }}</td>
                                <td>{{ $balance->warehouse?->vehicle?->plate_number ?? '-' }}</td>
                                <td>{{ $balance->product?->sku ?? '-' }}</td>
                                <td>{{ $balance->product?->name_ar ?? '-' }}</td>
                                <td>{{ $balance->product?->category?->name_ar ?? '-' }}</td>
                                <td>{{ $balance->product?->unit?->name_ar ?? '-' }}</td>
                                <td>{{ $balance->batch_number ?: '-' }}</td>
                                <td class="number">{{ $balance->expiry_date?->format('Y-m-d') ?? 'غير مسجل' }}</td>
                                <td>
                                    <span class="badge {{ $badgeClass }}">
                                        {{ ExpiryRiskReportsTable::expiryStatusLabel($status) }}
                                    </span>
                                </td>
                                <td class="number">{{ ExpiryRiskReportsTable::daysRemainingLabel($days) }}</td>
                                <td class="number">{{ $quantity($balance->quantity) }}</td>
                                <td class="number">{{ $money($balance->average_unit_cost) }}</td>
                                <td class="number">{{ $money(ExpiryRiskReportsTable::inventoryValue($balance)) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="empty" colspan="14">لا توجد أرصدة مطابقة للفلاتر الحالية.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="11">الإجمالي</td>
                            <td class="number">{{ $quantity($totals['quantity']) }}</td>
                            <td></td>
                            <td class="number">{{ $money($totals['inventory_value']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <footer class="report-footer">
            <span>تمت الطباعة بواسطة: {{ $generatedBy ?: '-' }}</span>
            <span>{{ now()->format('Y-m-d H:i') }}</span>
        </footer>
    </main>
</body>
</html>
