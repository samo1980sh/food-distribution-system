@php
    use App\Services\Imports\Excel\DistributionRouteExcelImportService;
    use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

    $uploadedFile = $get('excel_file');
    $preview = null;

    if ($uploadedFile instanceof TemporaryUploadedFile) {
        $preview = app(DistributionRouteExcelImportService::class)->analyze(
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
        .fd-route-import-preview {
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
        .dark .fd-route-import-preview {
            --fd-border: rgba(255, 255, 255, .10);
            --fd-soft: rgba(255, 255, 255, .04);
            --fd-text: #f8fafc;
            --fd-muted: #94a3b8;
            --fd-green: #4ade80;
            --fd-green-soft: rgba(34, 197, 94, .12);
            --fd-red: #f87171;
            --fd-red-soft: rgba(239, 68, 68, .12);
        }
        .fd-route-import-preview * { box-sizing: border-box; }
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
        .fd-import-metric { min-width: 0; padding: 10px 12px; border: 1px solid var(--fd-border); border-radius: 11px; background: #fff; }
        .dark .fd-import-metric { background: rgba(17, 24, 39, .32); }
        .fd-import-metric-label { color: var(--fd-muted); font-size: 11px; font-weight: 600; }
        .fd-import-metric-value { margin-top: 2px; color: var(--fd-text); font-size: 20px; font-weight: 800; line-height: 1.2; }
        .fd-import-metric-value.is-success { color: var(--fd-green); }
        .fd-import-metric-value.is-danger { color: var(--fd-red); }
        .fd-import-preview-block { padding: 0 18px 14px; }
        .fd-import-preview-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 13px 0 9px; }
        .fd-import-preview-heading { font-size: 12px; font-weight: 700; color: var(--fd-text); }
        .fd-import-preview-count { color: var(--fd-muted); font-size: 11px; }
        .fd-import-table-wrap { overflow-x: auto; border: 1px solid var(--fd-border); border-radius: 11px; }
        .fd-import-table { width: 100%; min-width: 1180px; border-collapse: collapse; }
        .fd-import-table th, .fd-import-table td { padding: 9px 10px; border-bottom: 1px solid var(--fd-border); text-align: right; vertical-align: middle; white-space: nowrap; }
        .fd-import-table th { background: var(--fd-soft); color: var(--fd-muted); font-size: 11px; font-weight: 700; }
        .fd-import-table td { color: var(--fd-text); font-size: 12px; }
        .fd-import-table tbody tr:last-child td { border-bottom: 0; }
        .fd-ltr { direction: ltr; unicode-bidi: plaintext; text-align: left !important; }
        .fd-empty { color: var(--fd-muted); }
        .fd-status { display: inline-flex; border-radius: 999px; padding: 4px 8px; font-size: 10px; font-weight: 700; }
        .fd-status.is-active { color: var(--fd-green); background: var(--fd-green-soft); }
        .fd-status.is-inactive { color: var(--fd-muted); background: var(--fd-soft); border: 1px solid var(--fd-border); }
        .fd-errors { margin: 0 18px 14px; border: 1px solid rgba(185, 28, 28, .20); border-radius: 11px; background: var(--fd-red-soft); overflow: hidden; }
        .fd-errors-title { padding: 10px 12px; color: var(--fd-red); font-size: 12px; font-weight: 800; border-bottom: 1px solid rgba(185, 28, 28, .14); }
        .fd-errors-list { margin: 0; padding: 10px 30px 10px 12px; color: var(--fd-red); font-size: 12px; line-height: 1.8; }
        .fd-import-policy { display: flex; gap: 10px; align-items: flex-start; margin: 0 18px 18px; padding: 11px 12px; border: 1px solid var(--fd-border); border-radius: 11px; background: var(--fd-soft); }
        .fd-import-policy strong { display: block; margin-bottom: 2px; color: var(--fd-text); font-size: 12px; }
        .fd-import-policy span { color: var(--fd-muted); font-size: 11px; line-height: 1.7; }
        @media (max-width: 720px) {
            .fd-import-head { align-items: flex-start; flex-direction: column; }
            .fd-import-metrics { grid-template-columns: 1fr; }
        }
    </style>

    <div class="fd-route-import-preview">
        <div class="fd-import-shell">
            <div class="fd-import-head">
                <div>
                    <h3 class="fd-import-title">معاينة ملف خطوط التوزيع</h3>
                    <p class="fd-import-subtitle">يتم التحقق من أكواد المراجع وأهلية الفريق وأيام الزيارة قبل السماح بالاستيراد.</p>
                </div>

                <span class="fd-import-state {{ $preview['valid'] ? 'is-success' : 'is-danger' }}">
                    {{ $preview['valid'] ? 'الملف جاهز للاستيراد' : 'الملف يحتاج تصحيحًا' }}
                </span>
            </div>

            <div class="fd-import-metrics">
                <div class="fd-import-metric">
                    <div class="fd-import-metric-label">صفوف البيانات</div>
                    <div class="fd-import-metric-value">{{ number_format($preview['row_count']) }}</div>
                </div>
                <div class="fd-import-metric">
                    <div class="fd-import-metric-label">الصفوف السليمة</div>
                    <div class="fd-import-metric-value is-success">{{ number_format($preview['valid_rows']) }}</div>
                </div>
                <div class="fd-import-metric">
                    <div class="fd-import-metric-label">الأخطاء</div>
                    <div class="fd-import-metric-value {{ $errorCount > 0 ? 'is-danger' : 'is-success' }}">{{ number_format($errorCount) }}</div>
                </div>
            </div>

            @if ($visibleRows !== [])
                <div class="fd-import-preview-block">
                    <div class="fd-import-preview-bar">
                        <div class="fd-import-preview-heading">أول {{ count($visibleRows) }} صفوف</div>
                        <div class="fd-import-preview-count">رقم الصف مطابق لرقم الصف الحقيقي في Excel</div>
                    </div>

                    <div class="fd-import-table-wrap">
                        <table class="fd-import-table">
                            <thead>
                                <tr>
                                    <th>صف Excel</th>
                                    <th>code</th>
                                    <th>الاسم</th>
                                    <th>area_code</th>
                                    <th>vehicle_code</th>
                                    <th>driver_code</th>
                                    <th>sales_representative_code</th>
                                    <th>visit_days</th>
                                    <th>الحالة</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($visibleRows as $row)
                                    <tr>
                                        <td class="fd-ltr">{{ $row['excel_row'] }}</td>
                                        <td class="fd-ltr">{{ $row['code'] }}</td>
                                        <td>{{ $row['name'] }}</td>
                                        <td class="fd-ltr">{{ $row['area_code'] }}</td>
                                        <td class="fd-ltr">{{ $row['vehicle_code'] ?? '—' }}</td>
                                        <td class="fd-ltr">{{ $row['driver_code'] ?? '—' }}</td>
                                        <td class="fd-ltr">{{ $row['sales_representative_code'] ?? '—' }}</td>
                                        <td class="fd-ltr">{{ $row['visit_days'] !== [] ? implode(',', $row['visit_days']) : '—' }}</td>
                                        <td>
                                            <span class="fd-status {{ $row['status'] === 'active' ? 'is-active' : 'is-inactive' }}">
                                                {{ $row['status'] }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if ($preview['errors'] !== [])
                <div class="fd-errors">
                    <div class="fd-errors-title">الأخطاء التي تمنع الاستيراد</div>
                    <ul class="fd-errors-list">
                        @foreach (array_slice($preview['errors'], 0, 12) as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="fd-import-policy">
                <div>
                    <strong>سياسة All-or-Nothing</strong>
                    <span>وجود خطأ واحد في أي صف يمنع استيراد الملف كاملًا. عند صلاحية الملف يعاد التحقق مرة أخرى داخل معاملة قاعدة بيانات قبل إنشاء خطوط التوزيع.</span>
                </div>
            </div>
        </div>
    </div>
@endif
