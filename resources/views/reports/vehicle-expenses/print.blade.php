@php
    $money = fn ($value): string => number_format((float) $value, 2) . ' ل.س';

    $expenseTypeLabels = [
        'fuel' => 'وقود',
        'maintenance' => 'صيانة',
        'washing' => 'غسيل',
        'fees' => 'رسوم',
        'parking' => 'موقف',
        'emergency' => 'طارئ',
        'other' => 'أخرى',
    ];

    $paymentMethodLabels = [
        'cash' => 'نقدي',
        'bank_transfer' => 'تحويل بنكي',
        'cheque' => 'شيك',
        'other' => 'أخرى',
    ];
@endphp

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مصروف السيارة {{ $expense->expense_number }}</title>
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
            width: min(900px, calc(100% - 30px));
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
        .print-button { background: #0f766e; color: #fff; }
        .close-button { background: #dce4e7; color: #26363b; }
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
            padding-bottom: 16px;
            border-bottom: 3px solid #0f766e;
        }
        h1, h2, p { margin-top: 0; }
        .brand h1 { margin-bottom: 3px; color: #0f766e; font-size: 24px; }
        .brand p, .document-title p { margin-bottom: 0; color: #607278; }
        .document-title { text-align: left; }
        .document-title h2 { margin-bottom: 3px; font-size: 19px; }
        .amount-box {
            margin-top: 18px;
            padding: 18px;
            border: 2px solid #0f766e;
            border-radius: 10px;
            background: #f1f8f7;
            text-align: center;
        }
        .amount-box span { display: block; color: #64767c; }
        .amount-box strong {
            display: block;
            margin-top: 4px;
            color: #0f5f58;
            font-size: 27px;
            direction: ltr;
        }
        .section { margin-top: 20px; }
        .section-title {
            margin-bottom: 10px;
            padding-right: 9px;
            border-right: 4px solid #0f766e;
            font-size: 15px;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }
        .field {
            padding: 10px 12px;
            border: 1px solid #dbe3e6;
            border-radius: 8px;
            background: #fafcfc;
        }
        .field span { display: block; color: #687980; font-size: 10px; }
        .field strong { display: block; margin-top: 3px; }
        .notes {
            min-height: 70px;
            padding: 12px;
            border: 1px solid #dbe3e6;
            border-radius: 8px;
            white-space: pre-wrap;
        }
        .signatures {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-top: 48px;
        }
        .signature {
            padding-top: 35px;
            border-top: 1px solid #8ca0a6;
            text-align: center;
        }
        .report-footer {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-top: 28px;
            padding-top: 10px;
            border-top: 1px solid #dbe3e6;
            color: #718187;
            font-size: 10px;
        }
        @media print {
            @page { size: A4 portrait; margin: 10mm; }
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet {
                width: 100%;
                margin: 0;
                padding: 0;
                border-radius: 0;
                box-shadow: none;
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
                <h2>مصروف سيارة معتمد</h2>
                <p>{{ $expense->expense_number }}</p>
            </div>
        </header>

        <div class="amount-box">
            <span>قيمة المصروف</span>
            <strong>{{ $money($expense->amount) }}</strong>
        </div>

        <section class="section">
            <h3 class="section-title">بيانات المصروف</h3>
            <div class="grid">
                <div class="field">
                    <span>رقم المصروف</span>
                    <strong>{{ $expense->expense_number }}</strong>
                </div>
                <div class="field">
                    <span>تاريخ المصروف</span>
                    <strong>{{ $expense->expense_date?->format('Y-m-d') ?? '-' }}</strong>
                </div>
                <div class="field">
                    <span>نوع المصروف</span>
                    <strong>{{ $expenseTypeLabels[$expense->expense_type] ?? $expense->expense_type }}</strong>
                </div>
                <div class="field">
                    <span>طريقة الدفع</span>
                    <strong>{{ $paymentMethodLabels[$expense->payment_method] ?? $expense->payment_method }}</strong>
                </div>
                <div class="field">
                    <span>الإيصال</span>
                    <strong>{{ filled($expense->receipt_path) ? 'مرفق' : 'غير مرفق' }}</strong>
                </div>
                <div class="field">
                    <span>المعتمد بواسطة</span>
                    <strong>{{ $expense->approvedBy?->name ?? '-' }}</strong>
                </div>
                <div class="field">
                    <span>تاريخ الاعتماد</span>
                    <strong>{{ $expense->approved_at?->format('Y-m-d H:i') ?? '-' }}</strong>
                </div>
                <div class="field">
                    <span>منشئ السجل</span>
                    <strong>{{ $expense->createdBy?->name ?? '-' }}</strong>
                </div>
            </div>
        </section>

        <section class="section">
            <h3 class="section-title">نطاق السيارة والتوزيع</h3>
            <div class="grid">
                <div class="field">
                    <span>السيارة</span>
                    <strong>{{ $expense->vehicle?->plate_number ?? '-' }}</strong>
                </div>
                <div class="field">
                    <span>المستودع</span>
                    <strong>{{ $expense->warehouse?->name ?? '-' }}</strong>
                </div>
                <div class="field">
                    <span>خط التوزيع</span>
                    <strong>{{ $expense->route?->name ?? '-' }}</strong>
                </div>
                <div class="field">
                    <span>السائق</span>
                    <strong>{{ $expense->driver?->name ?? '-' }}</strong>
                </div>
                <div class="field">
                    <span>المندوب</span>
                    <strong>{{ $expense->salesRepresentative?->name ?? '-' }}</strong>
                </div>
            </div>
        </section>

        <section class="section">
            <h3 class="section-title">الملاحظات</h3>
            <div class="notes">{{ $expense->notes ?: 'لا توجد ملاحظات.' }}</div>
        </section>

        <div class="signatures">
            <div class="signature">السائق / المندوب</div>
            <div class="signature">المشرف</div>
            <div class="signature">المحاسب</div>
        </div>

        <footer class="report-footer">
            <span>تمت الطباعة بواسطة: {{ $generatedBy ?: '-' }}</span>
            <span>{{ now()->format('Y-m-d H:i') }}</span>
        </footer>
    </main>
</body>
</html>
