@php
    use App\Services\Imports\Excel\VehicleExcelImportService;
    use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

    $uploadedFile = $get('excel_file');
    $preview = null;

    if ($uploadedFile instanceof TemporaryUploadedFile) {
        $preview = app(VehicleExcelImportService::class)->analyze(
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
        .fd-vehicle-import-preview {
            --fd-border: #e5e7eb;
            --fd-soft: #f8fafc;
            --fd-text: #111827;
            --fd-muted: #64748b;
            --fd-green: #15803d;
            --fd-green-soft: #f0fdf4;
            --fd-red: #b91c1c;
            --fd-red-soft: #fef2f2;
            margin-top: 2px;
            direction: rtl;
            color: var(--fd-text);
            font-size: 14px;
        }

        .dark .fd-vehicle-import-preview {
            --fd-border: rgba(255, 255, 255, .10);
            --fd-soft: rgba(255, 255, 255, .04);
            --fd-text: #f8fafc;
            --fd-muted: #94a3b8;
            --fd-green: #4ade80;
            --fd-green-soft: rgba(34, 197, 94, .12);
            --fd-red: #f87171;
            --fd-red-soft: rgba(239, 68, 68, .12);
        }

        .fd-vehicle-import-preview * { box-sizing: border-box; }
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
        .fd-import-metric-value { margin-top: 4px; font-size: 22px; line-height: 1; font-weight: 800; color: var(--fd-text); }
        .fd-import-metric-value.is-success { color: var(--fd-green); }
        .fd-import-metric-value.is-danger { color: var(--fd-red); }
        .fd-import-preview-block { padding: 16px 18px 8px; }
        .fd-import-preview-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 10px; }
        .fd-import-preview-heading { font-size: 13px; font-weight: 700; color: var(--fd-text); }
        .fd-import-preview-count { font-size: 11px; color: var(--fd-muted); }
        .fd-import-table-wrap { overflow-x: auto; border: 1px solid var(--fd-border); border-radius: 11px; background: #fff; }
        .dark .fd-import-table-wrap { background: rgba(17, 24, 39, .20); }
        .fd-import-table { width: 100%; min-width: 1420px; border-collapse: collapse; table-layout: fixed; }
        .fd-import-table th { padding: 10px 11px; border-bottom: 1px solid var(--fd-border); background: var(--fd-soft); text-align: right; font-size: 11px; font-weight: 700; color: var(--fd-muted); white-space: nowrap; }
        .fd-import-table td { padding: 11px; border-bottom: 1px solid var(--fd-border); vertical-align: top; font-size: 12px; color: var(--fd-text); word-break: break-word; }
        .fd-import-table tbody tr:last-child td { border-bottom: 0; }
        .fd-col-row { width: 74px; }
        .fd-col-code { width: 145px; }
        .fd-col-plate { width: 150px; }
        .fd-col-name { width: 180px; }
        .fd-col-type { width: 150px; }
        .fd-col-number { width: 120px; }
        .fd-col-date { width: 140px; }
        .fd-col-status { width: 125px; }
        .fd-ltr { direction: ltr; unicode-bidi: plaintext; text-align: left; font-variant-numeric: tabular-nums; }
        .fd-status { display: inline-flex; align-items: center; border-radius: 999px; padding: 4px 8px; font-size: 11px; font-weight: 700; background: var(--fd-soft); }
        .fd-status.active { color: var(--fd-green); background: var(--fd-green-soft); }
        .fd-status.maintenance { color: #b45309; background: #fffbeb; }
        .dark .fd-status.maintenance { color: #fbbf24; background: rgba(245, 158, 11, .12); }
        .fd-status.inactive { color: var(--fd-muted); }
        .fd-import-note { padding: 8px 18px 14px; color: var(--fd-muted); font-size: 11px; }
        .fd-import-result { display: flex; gap: 10px; margin: 8px 18px 18px; padding: 13px 14px; border-radius: 11px; line-height: 1.65; font-size: 12px; }
        .fd-import-result.is-success { color: var(--fd-green); background: var(--fd-green-soft); border: 1px solid color-mix(in srgb, var(--fd-green) 20%, transparent); }
        .fd-import-result.is-danger { color: var(--fd-red); background: var(--fd-red-soft); border: 1px solid color-mix(in srgb, var(--fd-red) 20%, transparent); }
        .fd-import-result-icon { flex: 0 0 auto; font-size: 16px; font-weight: 900; }
        .fd-import-errors { margin: 8px 0 0; padding-right: 18px; }
        .fd-import-errors li + li { margin-top: 4px; }
        @media (max-width: 720px) {
            .fd-import-head { align-items: flex-start; flex-direction: column; }
            .fd-import-metrics { grid-template-columns: 1fr; }
        }
    </style>

    <div class="fd-vehicle-import-preview">
        <div class="fd-import-shell">
            <div class="fd-import-head">
                <div>
                    <h3 class="fd-import-title">معاينة السيارات</h3>
                    <p class="fd-import-subtitle">المعاينة تعرض أول 10 صفوف، بينما يتم التحقق من الملف كاملًا قبل الاستيراد.</p>
                </div>
                <span class="fd-import-state {{ $preview['valid'] ? 'is-success' : 'is-danger' }}">
                    {{ $preview['valid'] ? 'جاهز للاستيراد' : 'يحتاج تصحيح' }}
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
                    <div class="fd-import-metric-label">الأخطاء</div>
                    <div class="fd-import-metric-value {{ $errorCount > 0 ? 'is-danger' : '' }}">{{ number_format($errorCount) }}</div>
                </div>
            </div>

            @if ($visibleRows !== [])
                <div class="fd-import-preview-block">
                    <div class="fd-import-preview-bar">
                        <div class="fd-import-preview-heading">صفوف Excel</div>
                        <div class="fd-import-preview-count">حتى 10 صفوف</div>
                    </div>

                    <div class="fd-import-table-wrap">
                        <table class="fd-import-table">
                            <thead>
                                <tr>
                                    <th class="fd-col-row">صف Excel</th>
                                    <th class="fd-col-code">code</th>
                                    <th class="fd-col-plate">plate_number</th>
                                    <th class="fd-col-name">الاسم / الوصف</th>
                                    <th class="fd-col-type">النوع</th>
                                    <th class="fd-col-number">السعة</th>
                                    <th class="fd-col-number">العداد</th>
                                    <th class="fd-col-date">انتهاء التأمين</th>
                                    <th class="fd-col-date">انتهاء الترخيص</th>
                                    <th class="fd-col-status">الحالة</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($visibleRows as $row)
                                    <tr>
                                        <td class="fd-ltr">{{ $row['excel_row'] }}</td>
                                        <td class="fd-ltr">{{ $row['code'] }}</td>
                                        <td class="fd-ltr">{{ $row['plate_number'] }}</td>
                                        <td>{{ $row['name'] ?: '—' }}</td>
                                        <td>{{ $row['vehicle_type'] ?: '—' }}</td>
                                        <td class="fd-ltr">{{ $row['capacity'] ?? '—' }}</td>
                                        <td class="fd-ltr">{{ $row['current_odometer'] ?? '—' }}</td>
                                        <td class="fd-ltr">{{ $row['insurance_expiry_date'] ?: '—' }}</td>
                                        <td class="fd-ltr">{{ $row['license_expiry_date'] ?: '—' }}</td>
                                        <td><span class="fd-status {{ $row['status'] }}">{{ $row['status'] }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($preview['row_count'] > count($visibleRows))
                        <div class="fd-import-note">تظهر {{ number_format(count($visibleRows)) }} صفوف فقط في المعاينة. سيتم فحص جميع الصفوف قبل الاستيراد.</div>
                    @endif
                </div>
            @endif

            @if ($preview['valid'])
                <div class="fd-import-result is-success">
                    <span class="fd-import-result-icon">✓</span>
                    <div><strong>الملف جاهز.</strong> سيتم إنشاء بيانات السيارات فقط دون إنشاء مستودعات أو خطوط أو حركات تشغيلية.</div>
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
