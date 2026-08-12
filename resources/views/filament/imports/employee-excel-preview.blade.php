@php
    use App\Services\Imports\Excel\EmployeeExcelImportService;
    use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

    $uploadedFile = $get('excel_file');
    $preview = null;

    if ($uploadedFile instanceof TemporaryUploadedFile) {
        $preview = app(EmployeeExcelImportService::class)->analyze(
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
        .fd-employee-import-preview {
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
        .dark .fd-employee-import-preview {
            --fd-border: rgba(255, 255, 255, .10);
            --fd-soft: rgba(255, 255, 255, .04);
            --fd-text: #f8fafc;
            --fd-muted: #94a3b8;
            --fd-green: #4ade80;
            --fd-green-soft: rgba(34, 197, 94, .12);
            --fd-red: #f87171;
            --fd-red-soft: rgba(239, 68, 68, .12);
        }
        .fd-employee-import-preview * { box-sizing: border-box; }
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
        .fd-import-table { width: 100%; min-width: 1000px; border-collapse: collapse; }
        .fd-import-table th, .fd-import-table td { padding: 9px 10px; border-bottom: 1px solid var(--fd-border); text-align: right; vertical-align: middle; white-space: nowrap; }
        .fd-import-table th { background: var(--fd-soft); color: var(--fd-muted); font-size: 11px; font-weight: 700; }
        .fd-import-table td { color: var(--fd-text); font-size: 12px; }
        .fd-import-table tbody tr:last-child td { border-bottom: 0; }
        .fd-ltr { direction: ltr; text-align: left !important; font-variant-numeric: tabular-nums; }
        .fd-type, .fd-status { display: inline-flex; align-items: center; border: 1px solid var(--fd-border); border-radius: 999px; padding: 4px 8px; background: var(--fd-soft); font-size: 11px; font-weight: 700; direction: ltr; }
        .fd-status.active { color: var(--fd-green); background: var(--fd-green-soft); border-color: transparent; }
        .fd-status.inactive { color: var(--fd-muted); }
        .fd-import-note { padding-top: 8px; color: var(--fd-muted); font-size: 11px; }
        .fd-import-result { display: flex; align-items: flex-start; gap: 9px; margin: 0 18px 18px; padding: 11px 12px; border-radius: 11px; line-height: 1.65; font-size: 12px; }
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

    <div class="fd-employee-import-preview">
        <div class="fd-import-shell">
            <div class="fd-import-head">
                <div>
                    <h3 class="fd-import-title">معاينة الموظفين</h3>
                    <p class="fd-import-subtitle">المعاينة تعرض أول 10 صفوف، بينما يتم التحقق من الملف كاملًا ومن تطابق حساب المستخدم والدور قبل الاستيراد.</p>
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
                                    <th>صف Excel</th>
                                    <th>employee_code</th>
                                    <th>الاسم</th>
                                    <th>الهاتف</th>
                                    <th>المسمى الوظيفي</th>
                                    <th>type</th>
                                    <th>user_email</th>
                                    <th>الحالة</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($visibleRows as $row)
                                    <tr>
                                        <td class="fd-ltr">{{ $row['excel_row'] }}</td>
                                        <td class="fd-ltr">{{ $row['employee_code'] }}</td>
                                        <td>{{ $row['name'] }}</td>
                                        <td class="fd-ltr">{{ $row['phone'] ?: '—' }}</td>
                                        <td>{{ $row['job_title'] ?: '—' }}</td>
                                        <td><span class="fd-type">{{ $row['type'] }}</span></td>
                                        <td class="fd-ltr">{{ $row['user_email'] ?: '—' }}</td>
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
                    <div><strong>الملف جاهز.</strong> سيتم إنشاء الموظفين وربط الحسابات الاختيارية فقط، دون إضافة أدوار جديدة أو تغيير حسابات المستخدمين.</div>
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
