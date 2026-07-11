@php
    $statusLabels = [
        'draft' => 'مسودة',
        'confirmed' => 'معتمد',
        'cancelled' => 'ملغي',
    ];

    $paymentMethodLabels = [
        'cash' => 'نقدي',
        'bank_transfer' => 'تحويل بنكي',
        'cheque' => 'شيك',
        'other' => 'أخرى',
    ];

    $money = fn ($value): string => number_format((float) $value, 2) . ' ل.س';
@endphp

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        سند تحصيل {{ $customerPayment->payment_number }}
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
            line-height: 1.8;
        }

        .toolbar,
        .sheet {
            width: min(900px, calc(100% - 32px));
            margin-right: auto;
            margin-left: auto;
        }

        .toolbar {
            display: flex;
            gap: 10px;
            margin-top: 20px;
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
            margin-top: 16px;
            margin-bottom: 40px;
            padding: 36px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 12px 35px rgba(15, 35, 43, 0.1);
        }

        .receipt-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 24px;
            padding-bottom: 20px;
            border-bottom: 3px solid #0f766e;
        }

        .brand h1,
        .receipt-title h2 {
            margin: 0;
        }

        .brand h1 {
            color: #0f766e;
            font-size: 27px;
        }

        .brand p,
        .receipt-title p {
            margin: 3px 0 0;
            color: #607278;
        }

        .receipt-title {
            text-align: left;
        }

        .document-number {
            direction: ltr;
            display: inline-block;
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
            margin-top: 25px;
        }

        .section-title {
            margin: 0 0 12px;
            padding-right: 10px;
            border-right: 4px solid #0f766e;
            font-size: 17px;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 11px;
        }

        .meta-item {
            padding: 10px 12px;
            border: 1px solid #dbe3e6;
            border-radius: 8px;
        }

        .meta-item span {
            display: block;
            color: #64767c;
            font-size: 11px;
        }

        .meta-item strong {
            display: block;
            margin-top: 3px;
            font-size: 14px;
        }

        .amount-box {
            padding: 18px;
            border: 2px solid #0f766e;
            border-radius: 10px;
            background: #f3faf9;
        }

        .amount-number {
            margin: 0;
            direction: ltr;
            text-align: center;
            color: #0f766e;
            font-size: 28px;
            font-weight: 700;
        }

        .amount-words {
            margin: 10px 0 0;
            padding-top: 10px;
            border-top: 1px dashed #9db8b4;
            text-align: center;
            font-size: 16px;
            font-weight: 700;
        }

        .details-table {
            width: 100%;
            border-collapse: collapse;
        }

        .details-table th,
        .details-table td {
            padding: 10px;
            border: 1px solid #cfdadd;
            vertical-align: middle;
        }

        .details-table th {
            width: 24%;
            background: #edf5f4;
            color: #24433f;
            text-align: right;
        }

        .number {
            direction: ltr;
            text-align: right;
        }

        .notes {
            min-height: 65px;
            padding: 13px;
            border: 1px solid #dbe3e6;
            border-radius: 8px;
            white-space: pre-wrap;
        }

        .signatures {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 30px;
            margin-top: 58px;
        }

        .signature {
            min-height: 85px;
            padding-top: 8px;
            border-top: 1px solid #64767c;
            text-align: center;
        }

        .receipt-footer {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-top: 28px;
            padding-top: 11px;
            border-top: 1px solid #dbe3e6;
            color: #718187;
            font-size: 10px;
        }

        @page {
            size: A4 portrait;
            margin: 12mm;
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

            .receipt-header {
                padding-bottom: 12px;
            }

            .section {
                margin-top: 14px;
            }

            .meta-grid {
                gap: 6px;
            }

            .meta-item {
                padding: 6px 8px;
            }

            .amount-box {
                padding: 12px;
            }

            .amount-number {
                font-size: 23px;
            }

            .amount-words {
                font-size: 13px;
            }

            .details-table th,
            .details-table td {
                padding: 6px;
            }

            .signatures {
                margin-top: 42px;
            }

            .meta-item,
            tr {
                break-inside: avoid;
            }
        }

        @media (max-width: 700px) {
            .receipt-header {
                display: block;
            }

            .receipt-title {
                margin-top: 15px;
                text-align: right;
            }

            .meta-grid,
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
    <div class="toolbar no-print">
        <button
            type="button"
            class="print-button"
            onclick="window.print()"
        >
            طباعة سند التحصيل
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
        <header class="receipt-header">
            <div class="brand">
                <h1>FreshRoute</h1>
                <p>نظام إدارة توزيع المواد الغذائية والأسطول</p>
            </div>

            <div class="receipt-title">
                <h2>سند قبض / تحصيل</h2>

                <p>
                    رقم السند:
                    <strong class="document-number">
                        {{ $customerPayment->payment_number }}
                    </strong>
                </p>

                <span class="status status-{{ $customerPayment->status }}">
                    {{ $statusLabels[$customerPayment->status] ?? $customerPayment->status }}
                </span>
            </div>
        </header>

        <section class="section">
            <h3 class="section-title">بيانات التحصيل</h3>

            <div class="meta-grid">
                <div class="meta-item">
                    <span>تاريخ التحصيل</span>
                    <strong>{{ $customerPayment->payment_date?->format('Y-m-d') ?? '-' }}</strong>
                </div>

                <div class="meta-item">
                    <span>العميل</span>
                    <strong>{{ $customerPayment->customer?->name ?? '-' }}</strong>
                </div>

                <div class="meta-item">
                    <span>رمز العميل</span>
                    <strong>{{ $customerPayment->customer?->code ?? '-' }}</strong>
                </div>

                <div class="meta-item">
                    <span>اسم المالك</span>
                    <strong>{{ $customerPayment->customer?->owner_name ?? '-' }}</strong>
                </div>

                <div class="meta-item">
                    <span>رقم الهاتف</span>
                    <strong dir="ltr">
                        {{ $customerPayment->customer?->phone ?? $customerPayment->customer?->mobile ?? '-' }}
                    </strong>
                </div>

                <div class="meta-item">
                    <span>مندوب التحصيل</span>
                    <strong>{{ $customerPayment->salesRepresentative?->name ?? '-' }}</strong>
                </div>
            </div>
        </section>

        <section class="section">
            <h3 class="section-title">المبلغ المقبوض</h3>

            <div class="amount-box">
                <p class="amount-number">
                    {{ $money($customerPayment->amount) }}
                </p>

                <p class="amount-words">
                    {{ $amountInWords }}
                </p>
            </div>
        </section>

        <section class="section">
            <h3 class="section-title">تفاصيل الدفع والمرجع</h3>

            <table class="details-table">
                <tbody>
                    <tr>
                        <th>طريقة الدفع</th>
                        <td>
                            {{ $paymentMethodLabels[$customerPayment->payment_method] ?? $customerPayment->payment_method }}
                        </td>

                        <th>رقم المرجع / الشيك</th>
                        <td class="number">
                            {{ $customerPayment->reference_number ?: '-' }}
                        </td>
                    </tr>

                    <tr>
                        <th>الفاتورة المرتبطة</th>
                        <td class="number">
                            {{ $customerPayment->salesInvoice?->invoice_number ?? '-' }}
                        </td>

                        <th>مبلغ الفاتورة</th>
                        <td class="number">
                            {{ $customerPayment->salesInvoice
                                ? $money($customerPayment->salesInvoice->total_amount)
                                : '-'
                            }}
                        </td>
                    </tr>

                    <tr>
                        <th>المستودع</th>
                        <td>{{ $customerPayment->warehouse?->name ?? '-' }}</td>

                        <th>السيارة</th>
                        <td class="number">
                            {{ $customerPayment->vehicle?->plate_number ?? '-' }}
                        </td>
                    </tr>

                    <tr>
                        <th>خط التوزيع</th>
                        <td>{{ $customerPayment->route?->name ?? '-' }}</td>

                        <th>تاريخ الاعتماد</th>
                        <td class="number">
                            {{ $customerPayment->confirmed_at?->format('Y-m-d H:i') ?? '-' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        @if (filled($customerPayment->notes))
            <section class="section">
                <h3 class="section-title">الملاحظات</h3>

                <div class="notes">{{ $customerPayment->notes }}</div>
            </section>
        @endif

        <section class="signatures">
            <div class="signature">
                توقيع العميل
            </div>

            <div class="signature">
                توقيع مندوب التحصيل
            </div>

            <div class="signature">
                توقيع المحاسب
            </div>
        </section>

        <footer class="receipt-footer">
            <span>
                أُنشئ بواسطة:
                {{ $customerPayment->creator?->name ?? '-' }}
            </span>

            <span>
                اعتُمد بواسطة:
                {{ $customerPayment->confirmer?->name ?? '-' }}
            </span>

            <span>
                تاريخ الطباعة:
                {{ now()->format('Y-m-d H:i') }}
            </span>
        </footer>
    </main>
</body>
</html>