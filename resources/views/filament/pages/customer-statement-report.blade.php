@php
    $money = fn ($value): string => number_format((float) $value, 2) . ' ل.س';

    $typeClasses = [
        'sales_invoice' => 'statement-type-invoice',
        'customer_payment' => 'statement-type-payment',
        'sales_return' => 'statement-type-return',
    ];

    $customerPhone = $customer['mobile'] ?? null;

    if (blank($customerPhone)) {
        $customerPhone = $customer['phone'] ?? null;
    }
@endphp

<x-filament-panels::page>
    <style>
        .customer-statement-shell {
            --statement-border: rgba(148, 163, 184, 0.32);
            --statement-surface: rgba(255, 255, 255, 0.72);
            --statement-surface-muted: rgba(148, 163, 184, 0.07);
            --statement-text: #0f172a;
            --statement-muted: #64748b;
            --statement-accent: #0f766e;
            --statement-accent-soft: rgba(13, 148, 136, 0.10);
            --statement-row-hover: rgba(13, 148, 136, 0.045);
            display: grid;
            gap: 18px;
        }

        .dark .customer-statement-shell {
            --statement-border: rgba(71, 85, 105, 0.78);
            --statement-surface: rgba(15, 23, 42, 0.78);
            --statement-surface-muted: rgba(30, 41, 59, 0.72);
            --statement-text: #f8fafc;
            --statement-muted: #cbd5e1;
            --statement-accent: #5eead4;
            --statement-accent-soft: rgba(13, 148, 136, 0.20);
            --statement-row-hover: rgba(45, 212, 191, 0.07);
        }

        .statement-query-panel,
        .statement-customer-header,
        .statement-ledger-panel,
        .statement-empty-state {
            border: 1px solid var(--statement-border);
            border-radius: 16px;
            background: var(--statement-surface);
            color: var(--statement-text);
        }

        .statement-query-panel {
            padding: 18px;
        }

        .statement-query-heading {
            margin-bottom: 16px;
        }

        .statement-query-heading h2,
        .statement-ledger-heading h2 {
            margin: 0;
            color: var(--statement-text);
            font-size: 16px;
            font-weight: 700;
        }

        .statement-query-heading p,
        .statement-ledger-heading p {
            margin: 4px 0 0;
            color: var(--statement-muted);
            font-size: 13px;
        }

        .customer-statement {
            display: grid;
            gap: 16px;
        }

        .statement-customer-header {
            display: flex;
            align-items: stretch;
            justify-content: space-between;
            gap: 20px;
            padding: 20px;
        }

        .statement-customer-main {
            display: flex;
            align-items: flex-start;
            min-width: 0;
            gap: 14px;
        }

        .statement-customer-avatar {
            display: grid;
            flex: 0 0 46px;
            width: 46px;
            height: 46px;
            place-items: center;
            border-radius: 14px;
            background: var(--statement-accent-soft);
            color: var(--statement-accent);
            font-size: 20px;
            font-weight: 800;
        }

        .statement-customer-copy {
            min-width: 0;
        }

        .statement-eyebrow {
            color: var(--statement-accent);
            font-size: 12px;
            font-weight: 700;
        }

        .statement-customer-name {
            margin: 3px 0 8px;
            color: var(--statement-text);
            font-size: 21px;
            font-weight: 800;
            line-height: 1.35;
        }

        .statement-customer-meta,
        .statement-customer-location {
            display: flex;
            flex-wrap: wrap;
            gap: 6px 14px;
            color: var(--statement-muted);
            font-size: 12px;
        }

        .statement-customer-location {
            margin-top: 6px;
        }

        .statement-customer-meta span,
        .statement-customer-location span {
            min-width: 0;
        }

        .statement-period {
            display: grid;
            flex: 0 0 auto;
            min-width: 210px;
            align-content: center;
            gap: 5px;
            padding: 14px 16px;
            border: 1px solid var(--statement-border);
            border-radius: 13px;
            background: var(--statement-surface-muted);
        }

        .statement-period span,
        .statement-period small {
            color: var(--statement-muted);
            font-size: 12px;
        }

        .statement-period strong {
            color: var(--statement-text);
            font-size: 14px;
            font-weight: 750;
            direction: ltr;
            text-align: right;
            white-space: nowrap;
        }

        .statement-summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
        }

        .statement-summary-card {
            min-width: 0;
            padding: 16px;
            border: 1px solid var(--statement-border);
            border-radius: 14px;
            background: var(--statement-surface);
            color: var(--statement-text);
        }

        .statement-summary-card span {
            display: block;
            color: var(--statement-muted);
            font-size: 12px;
        }

        .statement-summary-card strong {
            display: block;
            margin-top: 7px;
            color: var(--statement-text);
            font-size: 18px;
            font-weight: 800;
            direction: ltr;
            text-align: right;
            white-space: nowrap;
        }

        .statement-summary-card-closing {
            border-color: color-mix(in srgb, var(--statement-accent) 55%, transparent);
            background: var(--statement-accent-soft);
        }

        .statement-summary-card-closing span,
        .statement-summary-card-closing strong {
            color: var(--statement-accent);
        }

        .statement-secondary-metrics {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            border: 1px solid var(--statement-border);
            border-radius: 14px;
            overflow: hidden;
            background: var(--statement-surface);
        }

        .statement-secondary-item {
            min-width: 0;
            padding: 12px 14px;
            border-inline-start: 1px solid var(--statement-border);
        }

        .statement-secondary-item:first-child {
            border-inline-start: 0;
        }

        .statement-secondary-item span {
            display: block;
            color: var(--statement-muted);
            font-size: 11px;
        }

        .statement-secondary-item strong {
            display: block;
            margin-top: 4px;
            overflow: hidden;
            color: var(--statement-text);
            font-size: 13px;
            font-weight: 700;
            direction: ltr;
            text-align: right;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .statement-ledger-panel {
            overflow: hidden;
        }

        .statement-ledger-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 17px 18px;
            border-bottom: 1px solid var(--statement-border);
        }

        .statement-count {
            flex: 0 0 auto;
            padding: 5px 10px;
            border-radius: 999px;
            background: var(--statement-accent-soft);
            color: var(--statement-accent);
            font-size: 12px;
            font-weight: 700;
        }

        .statement-table-wrap {
            overflow-x: auto;
        }

        .statement-table {
            width: 100%;
            min-width: 920px;
            border-collapse: collapse;
            color: var(--statement-text);
        }

        .statement-table th,
        .statement-table td {
            padding: 11px 10px;
            border-bottom: 1px solid var(--statement-border);
            vertical-align: middle;
        }

        .statement-table th {
            background: var(--statement-surface-muted);
            color: var(--statement-muted);
            font-size: 12px;
            font-weight: 700;
            text-align: right;
            white-space: nowrap;
        }

        .statement-table tbody tr:hover {
            background: var(--statement-row-hover);
        }

        .statement-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .statement-number {
            direction: ltr;
            text-align: center !important;
            white-space: nowrap;
        }

        .statement-money {
            direction: ltr;
            text-align: right !important;
            white-space: nowrap;
        }

        .statement-type {
            display: inline-flex;
            align-items: center;
            min-height: 24px;
            padding: 3px 9px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 750;
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

        .dark .statement-type-invoice {
            color: #fcd34d;
        }

        .dark .statement-type-payment {
            color: #86efac;
        }

        .dark .statement-type-return {
            color: #93c5fd;
        }

        .statement-document {
            font-weight: 700;
        }

        .statement-note {
            display: block;
            margin-top: 4px;
            color: var(--statement-muted);
            font-size: 11px;
        }

        .statement-opening-row td,
        .statement-footer-row td {
            background: var(--statement-accent-soft);
            color: var(--statement-text);
            font-weight: 750;
        }

        .statement-footer-row td {
            border-top: 1px solid var(--statement-border);
            border-bottom: 0;
        }

        .statement-empty,
        .statement-empty-state {
            text-align: center;
        }

        .statement-empty {
            padding: 34px !important;
            color: var(--statement-muted);
        }

        .statement-empty-state {
            display: grid;
            justify-items: center;
            gap: 6px;
            padding: 34px 20px;
        }

        .statement-empty-state strong {
            color: var(--statement-text);
            font-size: 15px;
        }

        .statement-empty-state span {
            max-width: 520px;
            color: var(--statement-muted);
            font-size: 13px;
        }

        @media (max-width: 1200px) {
            .statement-secondary-metrics {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .statement-secondary-item:nth-child(4) {
                border-inline-start: 0;
            }

            .statement-secondary-item:nth-child(n + 4) {
                border-top: 1px solid var(--statement-border);
            }
        }

        @media (max-width: 900px) {
            .statement-customer-header {
                flex-direction: column;
            }

            .statement-period {
                width: 100%;
                min-width: 0;
            }

            .statement-summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .statement-query-panel,
            .statement-customer-header {
                padding: 15px;
            }

            .statement-summary-grid,
            .statement-secondary-metrics {
                grid-template-columns: 1fr;
            }

            .statement-secondary-item,
            .statement-secondary-item:nth-child(4) {
                border-inline-start: 0;
                border-top: 1px solid var(--statement-border);
            }

            .statement-secondary-item:first-child {
                border-top: 0;
            }

            .statement-summary-card strong {
                font-size: 16px;
            }

            .statement-ledger-heading {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>

    <div class="customer-statement-shell">
        <section class="statement-query-panel">
            <div class="statement-query-heading">
                <h2>خيارات كشف الحساب</h2>
                <p>حدد العميل والفترة المطلوبة، ثم اعرض الحركة والرصيد المتحرك ضمن كشف واحد.</p>
            </div>

            {{ $this->form }}
        </section>

        @if ($generated)
            <div class="customer-statement">
                <section class="statement-customer-header">
                    <div class="statement-customer-main">
                        <div class="statement-customer-avatar" aria-hidden="true">
                            {{ mb_substr((string) ($customer['name'] ?? 'ع'), 0, 1) }}
                        </div>

                        <div class="statement-customer-copy">
                            <span class="statement-eyebrow">بيانات العميل</span>
                            <h2 class="statement-customer-name">{{ $customer['name'] ?? '-' }}</h2>

                            <div class="statement-customer-meta">
                                <span>
                                    الرمز:
                                    <strong dir="ltr">{{ $customer['code'] ?? '-' }}</strong>
                                </span>

                                <span>المالك: {{ $customer['owner_name'] ?: '-' }}</span>

                                <span>
                                    الهاتف:
                                    <strong dir="ltr">{{ $customerPhone ?: '-' }}</strong>
                                </span>
                            </div>

                            <div class="statement-customer-location">
                                <span>المنطقة: {{ $customer['area'] ?: '-' }}</span>
                                <span>خط التوزيع: {{ $customer['route'] ?: '-' }}</span>
                                <span>العنوان: {{ $customer['address'] ?: '-' }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="statement-period">
                        <span>فترة كشف الحساب</span>
                        <strong>{{ $data['from'] ?? '-' }} — {{ $data['until'] ?? '-' }}</strong>
                        <small>{{ number_format($totals['transaction_count']) }} حركة معتمدة</small>
                    </div>
                </section>

                <section class="statement-summary-grid" aria-label="ملخص كشف الحساب">
                    <div class="statement-summary-card">
                        <span>الرصيد الافتتاحي</span>
                        <strong>{{ $money($totals['opening_balance']) }}</strong>
                    </div>

                    <div class="statement-summary-card">
                        <span>إجمالي المدين</span>
                        <strong>{{ $money($totals['period_debit']) }}</strong>
                    </div>

                    <div class="statement-summary-card">
                        <span>إجمالي الدائن</span>
                        <strong>{{ $money($totals['period_credit']) }}</strong>
                    </div>

                    <div class="statement-summary-card statement-summary-card-closing">
                        <span>الرصيد الختامي</span>
                        <strong>{{ $money($totals['closing_balance']) }}</strong>
                    </div>
                </section>

                <section class="statement-secondary-metrics" aria-label="تفاصيل حركة الفترة">
                    <div class="statement-secondary-item">
                        <span>إجمالي فواتير البيع</span>
                        <strong>{{ $money($totals['sales_total']) }}</strong>
                    </div>

                    <div class="statement-secondary-item">
                        <span>نقد الفواتير</span>
                        <strong>{{ $money($totals['invoice_cash_total']) }}</strong>
                    </div>

                    <div class="statement-secondary-item">
                        <span>تحصيلات العملاء</span>
                        <strong>{{ $money($totals['payments_total']) }}</strong>
                    </div>

                    <div class="statement-secondary-item">
                        <span>مرتجعات البيع</span>
                        <strong>{{ $money($totals['returns_total']) }}</strong>
                    </div>

                    <div class="statement-secondary-item">
                        <span>الحد الائتماني</span>
                        <strong>{{ $money($customer['credit_limit']) }}</strong>
                    </div>

                    <div class="statement-secondary-item">
                        <span>عدد الحركات</span>
                        <strong>{{ number_format($totals['transaction_count']) }}</strong>
                    </div>
                </section>

                <section class="statement-ledger-panel">
                    <header class="statement-ledger-heading">
                        <div>
                            <h2>حركة الحساب</h2>
                            <p>الفواتير والتحصيلات والمرتجعات مرتبة زمنيًا مع الرصيد بعد كل حركة.</p>
                        </div>

                        <span class="statement-count">
                            {{ number_format($totals['transaction_count']) }} حركة
                        </span>
                    </header>

                    <div class="statement-table-wrap">
                        <table class="statement-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>التاريخ</th>
                                    <th>نوع الحركة</th>
                                    <th>رقم المستند</th>
                                    <th>البيان</th>
                                    <th class="statement-money">مدين</th>
                                    <th class="statement-money">دائن</th>
                                    <th class="statement-money">الرصيد</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr class="statement-opening-row">
                                    <td class="statement-number">-</td>
                                    <td class="statement-number">{{ $data['from'] ?? '-' }}</td>
                                    <td colspan="5">الرصيد الافتتاحي قبل بداية الفترة</td>
                                    <td class="statement-money">{{ $money($totals['opening_balance']) }}</td>
                                </tr>

                                @forelse ($transactions as $transaction)
                                    <tr>
                                        <td class="statement-number">{{ $loop->iteration }}</td>
                                        <td class="statement-number">{{ $transaction['date'] }}</td>

                                        <td>
                                            <span class="statement-type {{ $typeClasses[$transaction['type']] ?? '' }}">
                                                {{ $transaction['type_label'] }}
                                            </span>
                                        </td>

                                        <td class="statement-number statement-document">
                                            {{ $transaction['document_number'] }}
                                        </td>

                                        <td>
                                            {{ $transaction['description'] }}

                                            @if (filled($transaction['notes']))
                                                <span class="statement-note">{{ $transaction['notes'] }}</span>
                                            @endif
                                        </td>

                                        <td class="statement-money">
                                            {{ (float) $transaction['debit'] > 0
                                                ? $money($transaction['debit'])
                                                : '-'
                                            }}
                                        </td>

                                        <td class="statement-money">
                                            {{ (float) $transaction['credit'] > 0
                                                ? $money($transaction['credit'])
                                                : '-'
                                            }}
                                        </td>

                                        <td class="statement-money">
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
                                    <td class="statement-money">{{ $money($totals['period_debit']) }}</td>
                                    <td class="statement-money">{{ $money($totals['period_credit']) }}</td>
                                    <td class="statement-money">{{ $money($totals['closing_balance']) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>
            </div>
        @else
            <section class="statement-empty-state">
                <strong>لم يتم إنشاء كشف حساب بعد</strong>
                <span>اختر العميل وحدد الفترة، ثم اضغط «عرض كشف الحساب» لإظهار البيانات والحركات.</span>
            </section>
        @endif
    </div>
</x-filament-panels::page>
