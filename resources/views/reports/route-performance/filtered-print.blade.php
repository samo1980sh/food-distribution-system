@php
    $money = fn ($v) => number_format((float) $v, 2) . ' ل.س';
    $qty = fn ($v) => number_format((float) $v, 3);
    $pct = fn ($v) => $v === null ? '-' : number_format((float) $v, 1) . '%';
    $unassignedCount = $unassigned['invoice_count'] + $unassigned['return_count'] + $unassigned['payment_count'] + $unassigned['expense_count'] + $unassigned['load_count'] + $unassigned['closing_count'];
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8"><title>تقرير أداء خطوط التوزيع</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#eef2f4;color:#17252a;font-family:Tahoma,Arial,sans-serif;font-size:7px;line-height:1.4}.toolbar,.sheet{width:min(1850px,calc(100% - 30px));margin:auto}.toolbar{display:flex;gap:10px;margin-top:18px}.toolbar button{border:0;border-radius:8px;padding:10px 22px;font:inherit;font-weight:700;cursor:pointer}.print{background:#0f766e;color:#fff}.close{background:#dce4e7;color:#26363b}.sheet{margin-top:14px;margin-bottom:35px;padding:20px;background:#fff;border-radius:12px;box-shadow:0 12px 35px rgba(15,35,43,.1)}header{display:flex;justify-content:space-between;gap:24px;padding-bottom:13px;border-bottom:3px solid #0f766e}h1,h2,h3,p{margin-top:0}h1{margin-bottom:3px;color:#0f766e;font-size:21px}.title{text-align:left}.muted{margin:3px 0 0;color:#607278}.section{margin-top:14px}.section h3{margin-bottom:7px;padding-right:8px;border-right:4px solid #0f766e;font-size:12px}.stats{display:grid;grid-template-columns:repeat(10,minmax(0,1fr));gap:6px}.box{padding:7px 8px;border:1px solid #dbe3e6;border-radius:7px;background:#f8fbfb}.box span{display:block;color:#64767c;font-size:6px}.box strong{display:block;margin-top:3px;color:#0f5f58;font-size:8.5px;direction:ltr;text-align:right}.danger{background:#fef2f2;border-color:#fecaca}.danger strong{color:#b91c1c}.warn{padding:9px 11px;border:1px solid #f59e0b;border-radius:8px;background:#fffbeb;color:#92400e}.filters{display:flex;flex-wrap:wrap;gap:6px}.filter{padding:4px 8px;border:1px solid #cfe1df;border-radius:999px;background:#f1f8f7}.filter b{color:#0f5f58}table{width:100%;border-collapse:collapse}th,td{padding:4px 3px;border:1px solid #cfdadd;vertical-align:middle}th{background:#edf5f4;color:#24433f;font-size:5.8px;white-space:nowrap}td{font-size:5.8px}.num{direction:ltr;text-align:center;white-space:nowrap}tbody tr:nth-child(even){background:#fafcfc}.noact{background:#f3f4f6!important;color:#6b7280}.loss{background:#fef2f2!important}tfoot td{background:#edf5f4;font-weight:700}.rank{display:inline-block;min-width:22px;padding:2px 5px;border-radius:999px;background:#dbeafe;color:#1e40af;font-weight:700}.badge{display:inline-block;padding:2px 5px;border-radius:999px;font-weight:700}.yes{background:#dcfce7;color:#166534}.no{background:#e5e7eb;color:#4b5563}.empty{padding:24px;text-align:center;color:#64767c}footer{display:flex;justify-content:space-between;margin-top:17px;padding-top:8px;border-top:1px solid #dbe3e6;color:#718187}
@media print{@page{size:A4 landscape;margin:5mm}body{background:#fff}.toolbar{display:none}.sheet{width:100%;margin:0;padding:0;border-radius:0;box-shadow:none}thead{display:table-header-group}tfoot{display:table-footer-group}tr{page-break-inside:avoid}}
</style>
</head>
<body>
<div class="toolbar"><button class="print" onclick="window.print()">طباعة</button><button class="close" onclick="window.close()">إغلاق</button></div>
<main class="sheet">
<header><div><h1>{{ config('app.name') }}</h1><p class="muted">نظام إدارة توزيع المواد الغذائية</p></div><div class="title"><h2>تقرير أداء خطوط التوزيع</h2><p class="muted">{{ $settings['from'] }} — {{ $settings['until'] }}</p></div></header>

<section class="section"><h3>الإجماليات</h3><div class="stats">
<div class="box"><span>الخطوط</span><strong>{{ $totals['routes_count'] }}</strong></div>
<div class="box"><span>ذات النشاط</span><strong>{{ $totals['routes_with_activity'] }}</strong></div>
<div class="box"><span>العملاء المسجلون</span><strong>{{ $totals['assigned_active_customers'] }}</strong></div>
<div class="box"><span>المخدومون</span><strong>{{ $totals['served_customers'] }}</strong></div>
<div class="box"><span>صافي المبيعات</span><strong>{{ $money($totals['net_sales']) }}</strong></div>
<div class="box"><span>الربح قبل المصاريف</span><strong>{{ $money($totals['gross_profit']) }}</strong></div>
<div class="box"><span>المصاريف</span><strong>{{ $money($totals['vehicle_expenses']) }}</strong></div>
<div class="box {{ $totals['net_contribution'] < 0 ? 'danger' : '' }}"><span>صافي المساهمة</span><strong>{{ $money($totals['net_contribution']) }}</strong></div>
<div class="box"><span>المقبوضات</span><strong>{{ $money($totals['total_collections']) }}</strong></div>
<div class="box"><span>فرق الصندوق</span><strong>{{ $money($totals['cash_difference']) }}</strong></div>
</div></section>

@if($unassignedCount > 0)
<section class="section"><div class="warn"><strong>تنبيه جودة البيانات:</strong> توجد مستندات معتمدة غير مربوطة بخط ولم تدخل في أداء أي خط:
فواتير {{ $unassigned['invoice_count'] }}،
مرتجعات {{ $unassigned['return_count'] }}،
تحصيلات {{ $unassigned['payment_count'] }} بقيمة {{ $money($unassigned['payment_amount']) }}،
مصاريف {{ $unassigned['expense_count'] }}،
تحميلات {{ $unassigned['load_count'] }}،
إغلاقات {{ $unassigned['closing_count'] }}.</div></section>
@endif

@if($filterSummary !== [])
<section class="section"><h3>الفلاتر المطبقة</h3><div class="filters">@foreach($filterSummary as $label=>$value)<span class="filter"><b>{{ $label }}:</b> {{ $value }}</span>@endforeach</div></section>
@endif

<section class="section"><h3>ترتيب الخطوط</h3>
<table><thead><tr><th>الترتيب</th><th>الرمز</th><th>الخط</th><th>النشاط</th><th>المنطقة</th><th>السيارة</th><th>السائق</th><th>المندوب</th><th class="num">المسجلون</th><th class="num">المخدومون</th><th class="num">التغطية</th><th class="num">الفواتير</th><th class="num">صافي المبيعات</th><th class="num">المرتجعات %</th><th class="num">الربح قبل المصاريف</th><th class="num">المصاريف</th><th class="num">صافي المساهمة</th><th class="num">الهامش</th><th class="num">المقبوضات</th><th class="num">تغطيتها</th><th class="num">التحميل</th><th class="num">فرق الصندوق</th></tr></thead><tbody>
@forelse($rankings as $r)
<tr class="{{ !$r['has_activity'] ? 'noact' : '' }} {{ $r['net_contribution'] < 0 ? 'loss' : '' }}"><td class="num"><span class="rank">{{ $r['rank'] }}</span></td><td>{{ $r['route']['code'] }}</td><td>{{ $r['route']['name'] }}</td><td><span class="badge {{ $r['has_activity'] ? 'yes' : 'no' }}">{{ $r['has_activity'] ? 'يوجد نشاط' : 'دون نشاط' }}</span></td><td>{{ $r['route']['area'] ?: '-' }}</td><td>{{ $r['route']['vehicle'] ?: '-' }}</td><td>{{ $r['route']['driver'] ?: '-' }}</td><td>{{ $r['route']['sales_representative'] ?: '-' }}</td><td class="num">{{ $r['assigned_active_customers'] }}</td><td class="num">{{ $r['served_customers'] }}</td><td class="num">{{ $pct($r['service_coverage_percent']) }}</td><td class="num">{{ $r['invoice_count'] }}</td><td class="num">{{ $money($r['net_sales']) }}</td><td class="num">{{ $pct($r['return_rate_percent']) }}</td><td class="num">{{ $money($r['gross_profit']) }}</td><td class="num">{{ $money($r['vehicle_expenses']) }}</td><td class="num">{{ $money($r['net_contribution']) }}</td><td class="num">{{ $pct($r['contribution_margin_percent']) }}</td><td class="num">{{ $money($r['total_collections']) }}</td><td class="num">{{ $pct($r['collection_coverage_percent']) }}</td><td class="num">{{ $qty($r['loaded_quantity']) }}</td><td class="num">{{ $money($r['cash_difference']) }}</td></tr>
@empty<tr><td class="empty" colspan="22">لا توجد خطوط مطابقة للفلاتر الحالية.</td></tr>@endforelse
</tbody><tfoot><tr><td colspan="8">الإجمالي</td><td class="num">{{ $totals['assigned_active_customers'] }}</td><td class="num">{{ $totals['served_customers'] }}</td><td class="num">{{ $pct($totals['service_coverage_percent']) }}</td><td class="num">{{ $totals['invoice_count'] }}</td><td class="num">{{ $money($totals['net_sales']) }}</td><td class="num">{{ $pct($totals['return_rate_percent']) }}</td><td class="num">{{ $money($totals['gross_profit']) }}</td><td class="num">{{ $money($totals['vehicle_expenses']) }}</td><td class="num">{{ $money($totals['net_contribution']) }}</td><td class="num">{{ $pct($totals['contribution_margin_percent']) }}</td><td class="num">{{ $money($totals['total_collections']) }}</td><td class="num">{{ $pct($totals['collection_coverage_percent']) }}</td><td class="num">{{ $qty($totals['loaded_quantity']) }}</td><td class="num">{{ $money($totals['cash_difference']) }}</td></tr></tfoot></table>
</section>
<footer><span>تمت الطباعة بواسطة: {{ $generatedBy ?: '-' }}</span><span>{{ now()->format('Y-m-d H:i') }}</span></footer>
</main>
</body>
</html>
