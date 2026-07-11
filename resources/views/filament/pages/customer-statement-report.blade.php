@php
    $money = fn ($value): string => number_format((float) $value, 2) . ' ل.س';

    $typeClasses = [
        'sales_invoice' => 'statement-type-invoice',
        'customer_payment' => 'statement-type-payment',
        'sales_return' => 'statement-type-return',
    ];
@endphp

<x-filament-panels::page>
    {{ $this->form }}

    @if ($generated)
        <style>
            .customer-statement {
                display: grid;
                gap: 18px;
            }

            .statement-panel {
                padding: 18px;
                border: 1px solid rgba(148, 163, 184, 0.35);
                border-radius: 12px;
                background: rgba(148, 163, 184, 0.06);
            }

            .statement-panel-title {
                margin: 0 0 14px;
                font-size: 17px;
                font-weight: 700;
            }

            .statement-customer-grid,
            .statement-totals-grid {
                display: grid;
                gap: 10px;
            }

            .statement-customer-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }

            .statement-totals-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }

            .statement-item,
            .statement-total {
                padding: 11px 13px;
                border: 1px solid rgba(148, 163, 184, 0.3);
                border-radius: 9px;
                background: rgba(148, 163, 184, 0.05);
            }

            .statement-item span,
            .statement-total span {
                display: block;
                opacity: 0.7;
                font-size: 12px;
            }

            .statement-item strong,
            .statement-total strong {
                display: block;
                margin-top: 4px;
                font-size: 14px;
            }

            .statement-total strong {
                direction: ltr;
                text-align: right;
            }

            .statement-total-closing {
                border-color: rgba(15, 118, 110, 0.6);
                background: rgba(15, 118, 110, 0.09);
            }

            .statement-table-wrap {
                overflow-x: auto;
            }

            .statement-table {
                width: 100%;
                border-collapse: collapse;
            }

            .statement-table th,
            .statement-table td {
                padding: 9px 8px;
                border: 1px solid rgba(148, 163, 184, 0.35);
                vertical-align: middle;
            }

            .statement-table th {
                background: rgba(15, 118, 110, 0.09);
                font-weight: 700;
                white-space: nowrap;
            }

            .statement-table tbody tr:nth-child(even) {
                background: rgba(148, 163, 184, 0.04);
            }

            .statement-number {
                direction: ltr;
                text-align: center;
                white-space: nowrap;
            }

            .statement-type {
                display: inline-block;
                padding: 3px 9px;
                border-radius: 999px;
                font-size: 11px;
                font-weight: 700;
                white-space: nowrap;
            }

            .statement-type-invoice {
                background: rgba(245, 158, 11, 0.16);
                color: #b45309;
            }

            .statement-type-payment {
                background: rgba(34, 197, 94, 0.16);
                color: #15803d;
            }

            .statement-type-return {
                background: rgba(59, 130, 246, 0.16);
                color: #1d4ed8;
            }

            .statement-opening-row td,
            .statement-footer-row td {
                background: rgba(15, 118, 110, 0.09);
                font-weight: 700;
            }

            .statement-empty {
                padding: 30px !important;
                text-align: center;
                opacity: 0.7;
            }

            .statement-note {
                display: block;
                margin-top: 3px;
                opacity: 0.65;
                font-size: 11px;
            }


            .dark .statement-panel {
                border-color: rgba(71, 85, 105, 0.75);
                background: rgba(15, 23, 42, 0.72);
                color: #e5e7eb;
            }

            .dark .statement-panel-title {
                color: #f8fafc;
            }

            .dark .statement-item,
            .dark .statement-total {
                border-color: rgba(71, 85, 105, 0.75);
                background: rgba(30, 41, 59, 0.72);
                color: #e5e7eb;
            }

            .dark .statement-item span,
            .dark .statement-total span {
                color: #cbd5e1;
                opacity: 1;
            }

            .dark .statement-item strong,
            .dark .statement-total strong {
                color: #f8fafc;
            }

            .dark .statement-total-closing {
                border-color: rgba(45, 212, 191, 0.65);
                background: rgba(13, 148, 136, 0.18);
            }

            .dark .statement-table {
                color: #e5e7eb;
            }

            .dark .statement-table th {
                border-color: rgba(71, 85, 105, 0.8);
                background: rgba(13, 148, 136, 0.2);
                color: #f8fafc;
            }

            .dark .statement-table td {
                border-color: rgba(71, 85, 105, 0.8);
                color: #e5e7eb;
            }

            .dark .statement-table tbody tr {
                background: rgba(15, 23, 42, 0.35);
            }

            .dark .statement-table tbody tr:nth-child(even) {
                background: rgba(30, 41, 59, 0.72);
            }

            .dark .statement-opening-row td,
            .dark .statement-footer-row td {
                background: rgba(13, 148, 136, 0.2);
                color: #f8fafc;
            }

            .dark .statement-type-invoice {
                background: rgba(245, 158, 11, 0.2);
                color: #fcd34d;
            }

            .dark .statement-type-payment {
                background: rgba(34, 197, 94, 0.2);
                color: #86efac;
            }

            .dark .statement-type-return {
                background: rgba(59, 130, 246, 0.2);
                color: #93c5fd;
            }

            .dark .statement-note {
                color: #cbd5e1;
                opacity: 1;
            }

            .dark .statement-empty {
                color: #cbd5e1;
                opacity: 1;
            }

            @media (max-width: 1100px) {
                .statement-customer-grid,
                .statement-totals-grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }

            @media (max-width: 650px) {
                .statement-customer-grid,
                .statement-totals-grid {
                    grid-template-columns: 1fr;
                }
            }
        </style>

        <div class="customer-statement">
            <section class="statement-panel">
                <h2 class="statement-panel-title">بيانات العميل</h2>

                <div class="statement-customer-grid">
                    <div class="statement-item">
                        <span>رمز العميل</span>
                        <strong dir="ltr">{{ $customer['code'] ?? '-' }}</strong>
                    </div>

                    <div class="statement-item">
                        <span>اسم العميل</span>
                        <strong>{{ $customer['name'] ?? '-' }}</strong>
                    </div>

                    <div class="statement-item">
                        <span>اسم المالك</span>
                        <strong>{{ $customer['owner_name'] ?: '-' }}</strong>
                    </div>

                    <div class="statement-item">
                        <span>الهاتف</span>
                        <strong dir="ltr">
                            {{ $customer['mobile'] ?: ($customer['phone'] ?: '-') }}
                        </strong>
                    </div>

                    <div class="statement-item">
                        <span>المنطقة</span>
                        <strong>{{ $customer['area'] ?: '-' }}</strong>
                    </div>

                    <div class="statement-item">
                        <span>خط التوزيع</span>
                        <strong>{{ $customer['route'] ?: '-' }}</strong>
                    </div>

                    <div class="statement-item">
                        <span>العنوان</span>
                        <strong>{{ $customer['address'] ?: '-' }}</strong>
                    </div>

                    <div class="statement-item">
                        <span>الحد الائتماني</span>
                        <strong dir="ltr">{{ $money($customer['credit_limit']) }}</strong>
                    </div>
                </div>
            </section>

            <section class="statement-panel">
                <h2 class="statement-panel-title">ملخص كشف الحساب</h2>

                <div class="statement-totals-grid">
                    <div class="statement-total">
                        <span>الرصيد الافتتاحي</span>
                        <strong>{{ $money($totals['opening_balance']) }}</strong>
                    </div>

                    <div class="statement-total">
                        <span>إجمالي المدين</span>
                        <strong>{{ $money($totals['period_debit']) }}</strong>
                    </div>

                    <div class="statement-total">
                        <span>إجمالي الدائن</span>
                        <strong>{{ $money($totals['period_credit']) }}</strong>
                    </div>

                    <div class="statement-total statement-total-closing">
                        <span>الرصيد الختامي</span>
                        <strong>{{ $money($totals['closing_balance']) }}</strong>
                    </div>

                    <div class="statement-total">
                        <span>إجمالي فواتير البيع</span>
                        <strong>{{ $money($totals['sales_total']) }}</strong>
                    </div>

                    <div class="statement-total">
                        <span>نقد الفواتير</span>
                        <strong>{{ $money($totals['invoice_cash_total']) }}</strong>
                    </div>

                    <div class="statement-total">
                        <span>تحصيلات العملاء</span>
                        <strong>{{ $money($totals['payments_total']) }}</strong>
                    </div>

                    <div class="statement-total">
                        <span>مرتجعات البيع</span>
                        <strong>{{ $money($totals['returns_total']) }}</strong>
                    </div>
                </div>
            </section>

            <section class="statement-panel">
                <h2 class="statement-panel-title">
                    حركة الحساب
                    — {{ number_format($totals['transaction_count']) }} حركة
                </h2>

                <div class="statement-table-wrap">
                    <table class="statement-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>التاريخ</th>
                                <th>نوع الحركة</th>
                                <th>رقم المستند</th>
                                <th>البيان</th>
                                <th class="statement-number">مدين</th>
                                <th class="statement-number">دائن</th>
                                <th class="statement-number">الرصيد</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr class="statement-opening-row">
                                <td class="statement-number">-</td>
                                <td class="statement-number">
                                    {{ $data['from'] ?? '-' }}
                                </td>
                                <td colspan="5">الرصيد الافتتاحي قبل بداية الفترة</td>
                                <td class="statement-number">
                                    {{ $money($totals['opening_balance']) }}
                                </td>
                            </tr>

                            @forelse ($transactions as $transaction)
                                <tr>
                                    <td class="statement-number">
                                        {{ $loop->iteration }}
                                    </td>

                                    <td class="statement-number">
                                        {{ $transaction['date'] }}
                                    </td>

                                    <td>
                                        <span class="statement-type {{ $typeClasses[$transaction['type']] ?? '' }}">
                                            {{ $transaction['type_label'] }}
                                        </span>
                                    </td>

                                    <td class="statement-number">
                                        {{ $transaction['document_number'] }}
                                    </td>

                                    <td>
                                        {{ $transaction['description'] }}

                                        @if (filled($transaction['notes']))
                                            <span class="statement-note">
                                                {{ $transaction['notes'] }}
                                            </span>
                                        @endif
                                    </td>

                                    <td class="statement-number">
                                        {{ (float) $transaction['debit'] > 0
                                            ? $money($transaction['debit'])
                                            : '-'
                                        }}
                                    </td>

                                    <td class="statement-number">
                                        {{ (float) $transaction['credit'] > 0
                                            ? $money($transaction['credit'])
                                            : '-'
                                        }}
                                    </td>

                                    <td class="statement-number">
                                        {{ $money($transaction['balance']) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="statement-empty">
                                        لا توجد حركات معتمدة للعميل ضمن الفترة المحددة.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>

                        <tfoot>
                            <tr class="statement-footer-row">
                                <td colspan="5">إجمالي الفترة والرصيد الختامي</td>

                                <td class="statement-number">
                                    {{ $money($totals['period_debit']) }}
                                </td>

                                <td class="statement-number">
                                    {{ $money($totals['period_credit']) }}
                                </td>

                                <td class="statement-number">
                                    {{ $money($totals['closing_balance']) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>
        </div>
    @endif
</x-filament-panels::page>