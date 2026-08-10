@php
    use App\Services\Imports\Excel\ProductExcelImportService;
    use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

    $uploadedFile = $get('excel_file');
    $preview = null;

    if ($uploadedFile instanceof TemporaryUploadedFile) {
        $preview = app(ProductExcelImportService::class)->analyze(
            $uploadedFile->getRealPath(),
            $uploadedFile->getClientOriginalName(),
        );
    }
@endphp

@if ($preview !== null)
    @php
        $visibleRows = array_slice($preview['rows'], 0, 10);
        $errorCount = count($preview['errors']);
    @endphp

    <style>
        .fd-product-import-preview {
            --fd-border: #e5e7eb;
            --fd-soft: #f8fafc;
            --fd-text: #111827;
            --fd-muted: #64748b;
            --fd-blue: #2563eb;
            --fd-blue-soft: #eff6ff;
            --fd-green: #15803d;
            --fd-green-soft: #f0fdf4;
            --fd-red: #b91c1c;
            --fd-red-soft: #fef2f2;
            margin-top: 2px;
            direction: rtl;
            color: var(--fd-text);
            font-size: 14px;
        }

        .dark .fd-product-import-preview {
            --fd-border: rgba(255, 255, 255, .10);
            --fd-soft: rgba(255, 255, 255, .04);
            --fd-text: #f8fafc;
            --fd-muted: #94a3b8;
            --fd-blue: #60a5fa;
            --fd-blue-soft: rgba(59, 130, 246, .12);
            --fd-green: #4ade80;
            --fd-green-soft: rgba(34, 197, 94, .12);
            --fd-red: #f87171;
            --fd-red-soft: rgba(239, 68, 68, .12);
        }

        .fd-product-import-preview * { box-sizing: border-box; }
        .fd-import-shell { overflow: hidden; border: 1px solid var(--fd-border); border-radius: 14px; background: #fff; }
        .dark .fd-import-shell { background: rgba(17, 24, 39, .35); }
        .fd-import-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 16px 18px; border-bottom: 1px solid var(--fd-border); }
        .fd-import-title { margin: 0; font-size: 15px; font-weight: 700; line-height: 1.5; color: var(--fd-text); }
        .fd-import-subtitle { margin: 3px 0 0; font-size: 12px; line-height: 1.6; color: var(--fd-muted); }
        .fd-import-state { display: inline-flex; align-items: center; gap: 7px; flex: 0 0 auto; border-radius: 999px; padding: 7px 11px; font-size: 12px; font-weight: 700; }
        .fd-import-state::before { content: ''; width: 7px; height: 7px; border-radius: 999px; background: currentColor; }
        .fd-import-state.is-success { color: var(--fd-green); background: var(--fd-green-soft); }
        .fd-import-state.is-danger { color: var(--fd-red); background: var(--fd-red-soft); }
        .fd-import-metrics { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; padding: 14px 18px; background: var(--fd-soft); border-bottom: 1px solid var(--fd-border); }
        .fd-import-metric { min-width: 0; padding: 12px 14px; border: 1px solid var(--fd-border); border-radius: 11px; background: #fff; }
        .dark .fd-import-metric { background: rgba(17, 24, 39, .25); }
        .fd-import-metric-label { font-size: 11px; font-weight: 600; color: var(--fd-muted); }
        .fd-import-metric-value { margin-top: 4px; font-size: 22px; line-height: 1; font-weight: 800; letter-spacing: -.02em; color: var(--fd-text); }
        .fd-import-metric-value.is-success { color: var(--fd-green); }
        .fd-import-metric-value.is-danger { color: var(--fd-red); }
        .fd-import-preview-block { padding: 16px 18px 8px; }
        .fd-import-preview-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 10px; }
        .fd-import-preview-heading { font-size: 13px; font-weight: 700; color: var(--fd-text); }
        .fd-import-preview-count { font-size: 11px; color: var(--fd-muted); }
        .fd-import-table-wrap { overflow-x: auto; border: 1px solid var(--fd-border); border-radius: 11px; background: #fff; }
        .dark .fd-import-table-wrap { background: rgba(17, 24, 39, .20); }
        .fd-import-table { width: 100%; min-width: 1510px; border-collapse: collapse; table-layout: fixed; }
        .fd-import-table th { padding: 10px 11px; border-bottom: 1px solid var(--fd-border); background: var(--fd-soft); text-align: right; font-size: 11px; font-weight: 700; color: var(--fd-muted); white-space: nowrap; }
        .fd-import-table td { padding: 11px; border-bottom: 1px solid var(--fd-border); vertical-align: middle; color: var(--fd-text); line-height: 1.5; }
        .fd-import-table tbody tr:last-child td { border-bottom: 0; }
        .fd-import-table tbody tr:hover { background: var(--fd-soft); }
        .fd-col-row { width: 78px; }
        .fd-col-sku { width: 145px; }
        .fd-col-barcode { width: 150px; }
        .fd-col-name { width: 210px; }
        .fd-col-ref { width: 140px; }
        .fd-col-number { width: 115px; }
        .fd-col-expiry { width: 100px; }
        .fd-col-status { width: 105px; }
        .fd-excel-row { display: inline-flex; align-items: center; justify-content: center; min-width: 30px; height: 26px; padding: 0 8px; border-radius: 7px; background: var(--fd-soft); color: var(--fd-muted); font-size: 11px; font-weight: 700; }
        .fd-code { direction: ltr; unicode-bidi: isolate; display: inline-block; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace; font-size: 12px; font-weight: 700; color: var(--fd-blue); }
        .fd-number { direction: ltr; unicode-bidi: isolate; display: inline-block; font-variant-numeric: tabular-nums; }
        .fd-status, .fd-expiry { display: inline-flex; align-items: center; justify-content: center; min-width: 64px; border-radius: 999px; padding: 5px 9px; font-size: 11px; font-weight: 700; }
        .fd-status.is-active, .fd-expiry.is-yes { background: var(--fd-green-soft); color: var(--fd-green); }
        .fd-status.is-inactive, .fd-expiry.is-no { background: var(--fd-soft); color: var(--fd-muted); }
        .fd-status.is-invalid { background: var(--fd-red-soft); color: var(--fd-red); }
        .fd-import-more { padding: 9px 12px; border-top: 1px solid var(--fd-border); font-size: 11px; line-height: 1.6; color: var(--fd-muted); background: var(--fd-soft); }
        .fd-import-result { display: flex; align-items: flex-start; gap: 10px; margin: 8px 18px 16px; padding: 12px 14px; border-radius: 11px; font-size: 12px; line-height: 1.7; }
        .fd-import-result-icon { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; flex: 0 0 22px; border-radius: 999px; font-size: 13px; font-weight: 800; }
        .fd-import-result.is-success { background: var(--fd-green-soft); color: var(--fd-green); }
        .fd-import-result.is-success .fd-import-result-icon { background: var(--fd-green); color: #fff; }
        .fd-import-result.is-danger { background: var(--fd-red-soft); color: var(--fd-red); }
        .fd-import-result.is-danger .fd-import-result-icon { background: var(--fd-red); color: #fff; }
        .fd-import-errors { margin: 7px 0 0; padding-right: 18px; }
        .fd-import-errors li + li { margin-top: 4px; }
        @media (max-width: 700px) {
            .fd-import-head { align-items: flex-start; flex-direction: column; }
            .fd-import-metrics { grid-template-columns: 1fr; }
            .fd-import-preview-bar { align-items: flex-start; flex-direction: column; }
        }
    </style>

    <div class="fd-product-import-preview">
        <div class="fd-import-shell">
            <div class="fd-import-head">
                <div>
                    <h3 class="fd-import-title">نتيجة فحص الملف</h3>
                    <p class="fd-import-subtitle">تم فحص رموز المنتجات والباركود والأسعار وربط التصنيف والوحدة قبل الحفظ.</p>
                </div>

                <span class="fd-import-state {{ $preview['valid'] ? 'is-success' : 'is-danger' }}">
                    {{ $preview['valid'] ? 'جاهز للاستيراد' : 'يحتاج معالجة' }}
                </span>
            </div>

            <div class="fd-import-metrics">
                <div class="fd-import-metric">
                    <div class="fd-import-metric-label">إجمالي الصفوف</div>
                    <div class="fd-import-metric-value">{{ number_format($preview['row_count']) }}</div>
                </div>
                <div class="fd-import-metric">
                    <div class="fd-import-metric-label">صفوف سليمة</div>
                    <div class="fd-import-metric-value is-success">{{ number_format($preview['valid_rows']) }}</div>
                </div>
                <div class="fd-import-metric">
                    <div class="fd-import-metric-label">أخطاء تحتاج معالجة</div>
                    <div class="fd-import-metric-value {{ $errorCount > 0 ? 'is-danger' : 'is-success' }}">{{ number_format($errorCount) }}</div>
                </div>
            </div>

            @if ($preview['rows'] !== [])
                <div class="fd-import-preview-block">
                    <div class="fd-import-preview-bar">
                        <div class="fd-import-preview-heading">معاينة المنتجات</div>
                        <div class="fd-import-preview-count">عرض {{ number_format(count($visibleRows)) }} من {{ number_format($preview['row_count']) }} صف</div>
                    </div>

                    <div class="fd-import-table-wrap">
                        <table class="fd-import-table">
                            <thead>
                                <tr>
                                    <th class="fd-col-row">صف Excel</th>
                                    <th class="fd-col-sku">SKU</th>
                                    <th class="fd-col-barcode">الباركود</th>
                                    <th class="fd-col-name">اسم المنتج</th>
                                    <th class="fd-col-ref">التصنيف</th>
                                    <th class="fd-col-ref">الوحدة</th>
                                    <th class="fd-col-number">شراء</th>
                                    <th class="fd-col-number">بيع</th>
                                    <th class="fd-col-number">جملة</th>
                                    <th class="fd-col-number">حد المخزون</th>
                                    <th class="fd-col-expiry">صلاحية</th>
                                    <th class="fd-col-status">الحالة</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($visibleRows as $row)
                                    <tr>
                                        <td><span class="fd-excel-row">{{ $row['excel_row'] }}</span></td>
                                        <td><span class="fd-code">{{ $row['sku'] !== '' ? $row['sku'] : '—' }}</span></td>
                                        <td><span class="fd-code">{{ $row['barcode'] ?? '—' }}</span></td>
                                        <td>{{ $row['name_ar'] !== '' ? $row['name_ar'] : '—' }}</td>
                                        <td><span class="fd-code">{{ $row['category_code'] ?? '—' }}</span></td>
                                        <td><span class="fd-code">{{ $row['unit_code'] ?? '—' }}</span></td>
                                        <td><span class="fd-number">{{ $row['purchase_price'] }}</span></td>
                                        <td><span class="fd-number">{{ $row['sale_price'] }}</span></td>
                                        <td><span class="fd-number">{{ $row['wholesale_price'] }}</span></td>
                                        <td><span class="fd-number">{{ $row['min_stock'] }}</span></td>
                                        <td>
                                            @if (is_bool($row['has_expiry']))
                                                <span class="fd-expiry {{ $row['has_expiry'] ? 'is-yes' : 'is-no' }}">{{ $row['has_expiry'] ? 'نعم' : 'لا' }}</span>
                                            @else
                                                <span class="fd-status is-invalid">{{ $row['has_expiry'] !== '' ? $row['has_expiry'] : '—' }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="fd-status {{ $row['status'] === 'active' ? 'is-active' : ($row['status'] === 'inactive' ? 'is-inactive' : 'is-invalid') }}">
                                                {{ $row['status'] === 'active' ? 'فعال' : ($row['status'] === 'inactive' ? 'غير فعال' : ($row['status'] !== '' ? $row['status'] : '—')) }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        @if ($preview['row_count'] > count($visibleRows))
                            <div class="fd-import-more">تظهر أول {{ number_format(count($visibleRows)) }} صفوف فقط في المعاينة. سيتم فحص جميع الصفوف قبل الاستيراد.</div>
                        @endif
                    </div>
                </div>
            @endif

            @if ($preview['valid'])
                <div class="fd-import-result is-success">
                    <span class="fd-import-result-icon">✓</span>
                    <div><strong>الملف جاهز.</strong> لن تُنشأ أي حركة مخزون أو رصيد؛ سيتم فقط إضافة بيانات المنتجات الأساسية والأسعار المرجعية.</div>
                </div>
            @else
                <div class="fd-import-result is-danger">
                    <span class="fd-import-result-icon">!</span>
                    <div>
                        <strong>لن يتم استيراد أي سجل حتى يتم إصلاح جميع الأخطاء.</strong>
                        @if ($preview['errors'] !== [])
                            <ul class="fd-import-errors">
                                @foreach (array_slice($preview['errors'], 0, 10) as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
@endif
