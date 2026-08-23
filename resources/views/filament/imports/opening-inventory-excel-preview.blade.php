@php
    $preview = $this->openingInventoryExcelPreview ?? [];
    $rows = $preview['rows'] ?? [];
    $errors = $preview['errors'] ?? [];
    $valid = (bool) ($preview['valid'] ?? false);
@endphp

<div id="fd-opening-inventory-import-preview" class="fd-opening-preview">
    <style>
        #fd-opening-inventory-import-preview {
            display: grid;
            gap: 1rem;
            direction: rtl;
        }

        #fd-opening-inventory-import-preview .fd-preview-empty,
        #fd-opening-inventory-import-preview .fd-preview-card,
        #fd-opening-inventory-import-preview .fd-preview-errors,
        #fd-opening-inventory-import-preview .fd-preview-table-wrap {
            border: 1px solid rgb(229 231 235);
            border-radius: .75rem;
            background: rgb(255 255 255);
        }

        #fd-opening-inventory-import-preview .fd-preview-empty {
            padding: 1rem;
            color: rgb(75 85 99);
            font-size: .875rem;
        }

        #fd-opening-inventory-import-preview .fd-preview-summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .75rem;
        }

        #fd-opening-inventory-import-preview .fd-preview-card {
            padding: .875rem 1rem;
        }

        #fd-opening-inventory-import-preview .fd-preview-card-label {
            color: rgb(107 114 128);
            font-size: .75rem;
            line-height: 1rem;
        }

        #fd-opening-inventory-import-preview .fd-preview-card-value {
            margin-top: .25rem;
            color: rgb(17 24 39);
            font-size: 1rem;
            line-height: 1.5rem;
            font-weight: 600;
        }

        #fd-opening-inventory-import-preview .fd-preview-card-value.is-valid {
            color: rgb(5 150 105);
        }

        #fd-opening-inventory-import-preview .fd-preview-card-value.is-invalid {
            color: rgb(220 38 38);
        }

        #fd-opening-inventory-import-preview .fd-preview-errors {
            padding: 1rem;
            border-color: rgb(254 202 202);
            background: rgb(254 242 242);
            color: rgb(185 28 28);
        }

        #fd-opening-inventory-import-preview .fd-preview-errors-title {
            font-weight: 600;
        }

        #fd-opening-inventory-import-preview .fd-preview-errors ul {
            margin: .5rem 0 0;
            padding-right: 1.25rem;
            list-style: disc;
            font-size: .875rem;
        }

        #fd-opening-inventory-import-preview .fd-preview-title {
            margin-bottom: .5rem;
            color: rgb(17 24 39);
            font-weight: 600;
        }

        #fd-opening-inventory-import-preview .fd-preview-table-wrap {
            overflow-x: auto;
        }

        #fd-opening-inventory-import-preview table.fd-preview-table {
            width: 100%;
            min-width: 980px;
            border-collapse: separate;
            border-spacing: 0;
            color: rgb(55 65 81);
            font-size: .8125rem;
            line-height: 1.25rem;
        }

        #fd-opening-inventory-import-preview .fd-preview-table th,
        #fd-opening-inventory-import-preview .fd-preview-table td {
            padding: .7rem .8rem;
            text-align: right;
            white-space: nowrap;
            border-bottom: 1px solid rgb(229 231 235);
            vertical-align: middle;
        }

        #fd-opening-inventory-import-preview .fd-preview-table th {
            background: rgb(249 250 251);
            color: rgb(55 65 81);
            font-weight: 600;
        }

        #fd-opening-inventory-import-preview .fd-preview-table tbody tr:last-child td {
            border-bottom: 0;
        }

        #fd-opening-inventory-import-preview .fd-preview-table tbody tr:hover td {
            background: rgb(249 250 251);
        }

        #fd-opening-inventory-import-preview .fd-preview-ltr {
            direction: ltr;
            unicode-bidi: isolate;
            display: inline-block;
            text-align: left;
        }

        #fd-opening-inventory-import-preview .fd-preview-note {
            margin-top: .5rem;
            color: rgb(107 114 128);
            font-size: .75rem;
        }

        @media (max-width: 767px) {
            #fd-opening-inventory-import-preview .fd-preview-summary {
                grid-template-columns: 1fr;
            }
        }
    </style>

    @if ($preview === [])
        <div class="fd-preview-empty">
            اختر ملف Excel لعرض معاينة الرصيد الافتتاحي قبل الاستيراد.
        </div>
    @else
        <div class="fd-preview-summary">
            <div class="fd-preview-card">
                <div class="fd-preview-card-label">عدد الصفوف</div>
                <div class="fd-preview-card-value">{{ number_format((int) ($preview['row_count'] ?? 0)) }}</div>
            </div>
            <div class="fd-preview-card">
                <div class="fd-preview-card-label">الصفوف السليمة</div>
                <div class="fd-preview-card-value">{{ number_format((int) ($preview['valid_rows'] ?? 0)) }}</div>
            </div>
            <div class="fd-preview-card">
                <div class="fd-preview-card-label">حالة الملف</div>
                <div class="fd-preview-card-value {{ $valid ? 'is-valid' : 'is-invalid' }}">
                    {{ $valid ? 'جاهز للاستيراد' : 'يحتاج تصحيحًا' }}
                </div>
            </div>
        </div>

        @if ($errors !== [])
            <div class="fd-preview-errors">
                <div class="fd-preview-errors-title">أخطاء الملف</div>
                <ul>
                    @foreach (array_slice($errors, 0, 8) as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                @if (count($errors) > 8)
                    <div class="fd-preview-note">يوجد {{ number_format(count($errors) - 8) }} خطأ إضافي.</div>
                @endif
            </div>
        @endif

        @if ($rows !== [])
            <div>
                <div class="fd-preview-title">معاينة الرصيد الافتتاحي</div>
                <div class="fd-preview-table-wrap">
                    <table class="fd-preview-table">
                        <thead>
                            <tr>
                                <th>صف Excel</th>
                                <th>المستودع</th>
                                <th>SKU</th>
                                <th>الكمية</th>
                                <th>التشغيلة</th>
                                <th>الصلاحية</th>
                                <th>تكلفة الوحدة</th>
                                <th>تاريخ الحركة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (array_slice($rows, 0, 10) as $row)
                                <tr>
                                    <td><span class="fd-preview-ltr">{{ $row['excel_row'] }}</span></td>
                                    <td><span class="fd-preview-ltr">{{ $row['warehouse_code'] }}</span></td>
                                    <td><span class="fd-preview-ltr">{{ $row['sku'] }}</span></td>
                                    <td><span class="fd-preview-ltr">{{ $row['quantity'] }}</span></td>
                                    <td><span class="fd-preview-ltr">{{ $row['batch_number'] ?: '—' }}</span></td>
                                    <td><span class="fd-preview-ltr">{{ $row['expiry_date'] ?: '—' }}</span></td>
                                    <td><span class="fd-preview-ltr">{{ $row['unit_cost'] }}</span></td>
                                    <td><span class="fd-preview-ltr">{{ $row['movement_date'] ?: '—' }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if (count($rows) > 10)
                    <div class="fd-preview-note">تظهر أول 10 صفوف فقط في المعاينة.</div>
                @endif
            </div>
        @endif
    @endif
</div>
