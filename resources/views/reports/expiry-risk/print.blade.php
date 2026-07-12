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
    <title>بطاقة صلاحية الرصيد #{{ $balance->id }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #eef2f4;
            color: #17252a;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
            line-height: 1.6;
        }
        .toolbar, .sheet {
            width: min(920px, calc(100% - 30px));
            margin-right: auto;
            margin-left: auto;
        }
        .toolbar { display: flex; gap: 10px; margin-top: 18px; }
        .toolbar button {
            border: 0; border-radius: 8px; padding: 10px 22px;
            font: inherit; font-weight: 700; cursor: pointer;
        }
        .print-button { background: #0f766e; color: #fff; }
        .close-button { background: #dce4e7; color: #26363b; }
        .sheet {
            margin-top: 14px; margin-bottom: 35px; padding: 28px;
            background: #fff; border-radius: 12px;
            box-shadow: 0 12px 35px rgba(15, 35, 43, 0.1);
        }
        .report-header {
            display: flex; justify-content: space-between; gap: 24px;
            padding-bottom: 16px; border-bottom: 3px solid #0f766e;
        }
        h1, h2, h3, p { margin-top: 0; }
        .brand h1 { margin-bottom: 3px; color: #0f766e; font-size: 24px; }
        .brand p, .document-title p { margin-bottom: 0; color: #607278; }
        .document-title { text-align: left; }
        .document-title h2 { margin-bottom: 3px; font-size: 19px; }
        .status-box {
            margin-top: 18px; padding: 18px; border: 2px solid #b45309;
            border-radius: 10px; background: #fffbeb; text-align: center;
        }
        .status-box strong { display: block; color: #92400e; font-size: 23px; }
        .status-box span { display: block; margin-top: 4px; color: #7c5a19; }
        .section { margin-top: 20px; }
        .section-title {
            margin-bottom: 10px; padding-right: 9px;
            border-right: 4px solid #0f766e; font-size: 15px;
        }
        .grid {
            display: grid; grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }
        .field {
            padding: 10px 12px; border: 1px solid #dbe3e6;
            border-radius: 8px; background: #fafcfc;
        }
        .field span { display: block; color: #687980; font-size: 10px; }
        .field strong { display: block; margin-top: 3px; }
        .number { direction: ltr; text-align: right; }
        .warning {
            margin-top: 18px; padding: 11px 13px;
            border-right: 4px solid #dc2626; background: #fef2f2; color: #991b1b;
        }
        .signatures {
            display: grid; grid-template-columns: repeat(3, 1fr);
            gap: 30px; margin-top: 48px;
        }
        .signature {
            padding-top: 35px; border-top: 1px solid #8ca0a6; text-align: center;
        }
        .report-footer {
            display: flex; justify-content: space-between; gap: 20px;
            margin-top: 28px; padding-top: 10px; border-top: 1px solid #dbe3e6;
            color: #718187; font-size: 10px;
        }
        @media print {
            @page { size: A4 portrait; margin: 10mm; }
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet {
                width: 100%; margin: 0; padding: 0;
                border-radius: 0; box-shadow: none;
            }
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
                <h2>بطاقة صلاحية رصيد</h2>
                <p>رصيد رقم #{{ $balance->id }}</p>
            </div>
        </header>

        <div class="status-box">
            <strong>{{ ExpiryRiskReportsTable::expiryStatusLabel($status) }}</strong>
            <span>{{ ExpiryRiskReportsTable::daysRemainingLabel($daysRemaining) }}</span>
        </div>

        @if ($status === 'missing')
            <div class="warning">
                هذا المنتج يتطلب تتبع الصلاحية، لكن تاريخ الصلاحية غير مسجل لهذا الرصيد.
            </div>
        @endif

        <section class="section">
            <h3 class="section-title">بيانات المنتج</h3>
            <div class="grid">
                <div class="field">
                    <span>رمز المنتج</span>
                    <strong>{{ $balance->product?->sku ?? '-' }}</strong>
                </div>
                <div class="field">
                    <span>اسم المنتج</span>
                    <strong>{{ $balance->product?->name_ar ?? '-' }}</strong>
                </div>
                <div class="field">
                    <span>التصنيف</span>
                    <strong>{{ $balance->product?->category?->name_ar ?? '-' }}</strong>
                </div>
                <div class="field">
                    <span>الوحدة</span>
                    <strong>{{ $balance->product?->unit?->name_ar ?? '-' }}</strong>
                </div>
                <div class="field">
                    <span>رقم التشغيلة</span>
                    <strong>{{ $balance->batch_number ?: '-' }}</strong>
                </div>
                <div class="field">
                    <span>تاريخ الصلاحية</span>
                    <strong>{{ $balance->expiry_date?->format('Y-m-d') ?? 'غير مسجل' }}</strong>
                </div>
            </div>
        </section>

        <section class="section">
            <h3 class="section-title">بيانات المخزون</h3>
            <div class="grid">
                <div class="field">
                    <span>المستودع</span>
                    <strong>{{ $balance->warehouse?->name ?? '-' }}</strong>
                </div>
                <div class="field">
                    <span>نوع المستودع</span>
                    <strong>{{ ExpiryRiskReportsTable::warehouseTypeLabel($balance->warehouse?->type) }}</strong>
                </div>
                <div class="field">
                    <span>السيارة</span>
                    <strong>{{ $balance->warehouse?->vehicle?->plate_number ?? '-' }}</strong>
                </div>
                <div class="field">
                    <span>الكمية الحالية</span>
                    <strong class="number">{{ $quantity($balance->quantity) }}</strong>
                </div>
                <div class="field">
                    <span>متوسط تكلفة الوحدة</span>
                    <strong class="number">{{ $money($balance->average_unit_cost) }}</strong>
                </div>
                <div class="field">
                    <span>قيمة الرصيد بالتكلفة</span>
                    <strong class="number">{{ $money($inventoryValue) }}</strong>
                </div>
                <div class="field">
                    <span>آخر تحديث</span>
                    <strong>{{ $balance->updated_at?->format('Y-m-d H:i') ?? '-' }}</strong>
                </div>
            </div>
        </section>

        <div class="signatures">
            <div class="signature">أمين المستودع</div>
            <div class="signature">المشرف</div>
            <div class="signature">الإدارة</div>
        </div>

        <footer class="report-footer">
            <span>تمت الطباعة بواسطة: {{ $generatedBy ?: '-' }}</span>
            <span>{{ now()->format('Y-m-d H:i') }}</span>
        </footer>
    </main>
</body>
</html>
