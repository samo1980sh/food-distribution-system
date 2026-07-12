@php
    use App\Services\Reports\RoutePerformanceReportService;
    $s = $report['summary'];
    $set = $report['settings'];
    $money = fn ($v) => number_format((float) $v, 2) . ' ل.س';
    $qty = fn ($v) => number_format((float) $v, 3);
    $pct = fn ($v) => $v === null ? '-' : number_format((float) $v, 1) . '%';
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>تفصيل أداء {{ $s['route']['name'] }}</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#eef2f4;color:#17252a;font-family:Tahoma,Arial,sans-serif;font-size:8px;line-height:1.45}
.toolbar,.sheet{width:min(1650px,calc(100% - 30px));margin:auto}.toolbar{display:flex;gap:10px;margin-top:18px}.toolbar button{border:0;border-radius:8px;padding:10px 22px;font:inherit;font-weight:700;cursor:pointer}
.print{background:#0f766e;color:#fff}.close{background:#dce4e7;color:#26363b}.sheet{margin-top:14px;margin-bottom:35px;padding:22px;background:#fff;border-radius:12px;box-shadow:0 12px 35px rgba(15,35,43,.1)}
header{display:flex;justify-content:space-between;gap:24px;padding-bottom:14px;border-bottom:3px solid #0f766e}h1,h2,h3,p{margin-top:0}h1{margin-bottom:3px;color:#0f766e;font-size:22px}.muted{margin-bottom:0;color:#607278}.title{text-align:left}.title h2{margin-bottom:3px;font-size:18px}
.section{margin-top:15px}.section h3{margin-bottom:8px;padding-right:8px;border-right:4px solid #0f766e;font-size:13px}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:7px}.stats{display:grid;grid-template-columns:repeat(8,minmax(0,1fr));gap:7px}
.box{padding:8px 9px;border:1px solid #dbe3e6;border-radius:8px;background:#f8fbfb}.box span{display:block;color:#64767c;font-size:7px}.box strong{display:block;margin-top:3px}.stats .box strong{color:#0f5f58;font-size:10px;direction:ltr;text-align:right}.danger{background:#fef2f2;border-color:#fecaca}.danger strong{color:#b91c1c}
table{width:100%;border-collapse:collapse}th,td{padding:5px 4px;border:1px solid #cfdadd;vertical-align:middle}th{background:#edf5f4;color:#24433f;font-size:6.8px;white-space:nowrap}td{font-size:6.8px}tbody tr:nth-child(even){background:#fafcfc}.num{direction:ltr;text-align:center;white-space:nowrap}.empty{padding:20px;text-align:center;color:#64767c}
footer{display:flex;justify-content:space-between;gap:20px;margin-top:18px;padding-top:9px;border-top:1px solid #dbe3e6;color:#718187;font-size:8px}
@media print{@page{size:A4 landscape;margin:6mm}body{background:#fff}.toolbar{display:none}.sheet{width:100%;margin:0;padding:0;border-radius:0;box-shadow:none}thead{display:table-header-group}tr{page-break-inside:avoid}}
</style>
</head>
<body>
<div class="toolbar"><button class="print" onclick="window.print()">طباعة</button><button class="close" onclick="window.close()">إغلاق</button></div>
<main class="sheet">
<header><div><h1>{{ config('app.name') }}</h1><p class="muted">نظام إدارة توزيع المواد الغذائية</p></div><div class="title"><h2>تفصيل أداء خط توزيع</h2><p class="muted">{{ $s['route']['code'] }} — {{ $s['route']['name'] }}</p></div></header>

<section class="section">
<div class="grid">
<div class="box"><span>الخط</span><strong>{{ $s['route']['name'] }}</strong></div>
<div class="box"><span>المنطقة</span><strong>{{ $s['route']['area'] ?: '-' }}</strong></div>
<div class="box"><span>السيارة</span><strong>{{ $s['route']['vehicle'] ?: '-' }}</strong></div>
<div class="box"><span>النشاط</span><strong>{{ $s['has_activity'] ? 'يوجد نشاط' : 'دون نشاط' }}</strong></div>
<div class="box"><span>السائق</span><strong>{{ $s['route']['driver'] ?: '-' }}</strong></div>
<div class="box"><span>المندوب</span><strong>{{ $s['route']['sales_representative'] ?: '-' }}</strong></div>
<div class="box"><span>الفترة</span><strong>{{ $set['from'] }} — {{ $set['until'] }}</strong></div>
<div class="box"><span>حالة الخط</span><strong>{{ RoutePerformanceReportService::statusLabel($s['route']['status']) }}</strong></div>
</div>
</section>

<section class="section"><h3>المؤشرات التشغيلية</h3>
<div class="stats">
<div class="box"><span>العملاء المسجلون</span><strong>{{ $s['assigned_active_customers'] }}</strong></div>
<div class="box"><span>العملاء المخدومون</span><strong>{{ $s['served_customers'] }}</strong></div>
<div class="box"><span>تغطية العملاء</span><strong>{{ $pct($s['service_coverage_percent']) }}</strong></div>
<div class="box"><span>الفواتير</span><strong>{{ $s['invoice_count'] }}</strong></div>
<div class="box"><span>المرتجعات</span><strong>{{ $s['return_count'] }}</strong></div>
<div class="box"><span>التحصيلات</span><strong>{{ $s['payment_count'] }}</strong></div>
<div class="box"><span>التحميلات</span><strong>{{ $s['load_count'] }}</strong></div>
<div class="box"><span>الإغلاقات</span><strong>{{ $s['closing_count'] }}</strong></div>
</div>
</section>

<section class="section"><h3>المؤشرات المالية</h3>
<div class="stats">
<div class="box"><span>صافي المبيعات</span><strong>{{ $money($s['net_sales']) }}</strong></div>
<div class="box"><span>صافي الكمية</span><strong>{{ $qty($s['net_quantity']) }}</strong></div>
<div class="box {{ $s['gross_profit'] < 0 ? 'danger' : '' }}"><span>الربح قبل المصاريف</span><strong>{{ $money($s['gross_profit']) }}</strong></div>
<div class="box"><span>المصاريف</span><strong>{{ $money($s['vehicle_expenses']) }}</strong></div>
<div class="box {{ $s['net_contribution'] < 0 ? 'danger' : '' }}"><span>صافي المساهمة</span><strong>{{ $money($s['net_contribution']) }}</strong></div>
<div class="box {{ ($s['contribution_margin_percent'] ?? 0) < 0 ? 'danger' : '' }}"><span>هامش المساهمة</span><strong>{{ $pct($s['contribution_margin_percent']) }}</strong></div>
<div class="box"><span>إجمالي المقبوضات</span><strong>{{ $money($s['total_collections']) }}</strong></div>
<div class="box"><span>تغطية المقبوضات</span><strong>{{ $pct($s['collection_coverage_percent']) }}</strong></div>
</div>
</section>

<section class="section"><h3>الفواتير المعتمدة</h3>
<table><thead><tr><th>الفاتورة</th><th>التاريخ</th><th>العميل</th><th>نوع الدفع</th><th class="num">الكمية</th><th class="num">الإجمالي</th><th class="num">النقد</th><th class="num">التكلفة</th><th class="num">الربح</th></tr></thead><tbody>
@forelse($report['invoices'] as $r)<tr><td>{{ $r['number'] }}</td><td class="num">{{ $r['date'] }}</td><td>{{ $r['customer'] ?: '-' }}</td><td>{{ RoutePerformanceReportService::paymentTypeLabel($r['payment_type']) }}</td><td class="num">{{ $qty($r['quantity']) }}</td><td class="num">{{ $money($r['total']) }}</td><td class="num">{{ $money($r['cash']) }}</td><td class="num">{{ $money($r['cost']) }}</td><td class="num">{{ $money($r['profit']) }}</td></tr>
@empty<tr><td class="empty" colspan="9">لا توجد فواتير معتمدة خلال الفترة.</td></tr>@endforelse
</tbody></table></section>

<section class="section"><h3>المرتجعات المعتمدة</h3>
<table><thead><tr><th>المرتجع</th><th>التاريخ</th><th>العميل</th><th>الفاتورة</th><th>السبب</th><th class="num">الكمية</th><th class="num">القيمة</th><th class="num">التكلفة</th></tr></thead><tbody>
@forelse($report['returns'] as $r)<tr><td>{{ $r['number'] }}</td><td class="num">{{ $r['date'] }}</td><td>{{ $r['customer'] ?: '-' }}</td><td>{{ $r['invoice'] ?: '-' }}</td><td>{{ $r['reason'] ?: '-' }}</td><td class="num">{{ $qty($r['quantity']) }}</td><td class="num">{{ $money($r['total']) }}</td><td class="num">{{ $money($r['cost']) }}</td></tr>
@empty<tr><td class="empty" colspan="8">لا توجد مرتجعات معتمدة خلال الفترة.</td></tr>@endforelse
</tbody></table></section>

<section class="section"><h3>التحصيلات المربوطة بالخط</h3>
<table><thead><tr><th>التحصيل</th><th>التاريخ</th><th>العميل</th><th>الفاتورة</th><th>الطريقة</th><th class="num">المبلغ</th></tr></thead><tbody>
@forelse($report['payments'] as $r)<tr><td>{{ $r['number'] }}</td><td class="num">{{ $r['date'] }}</td><td>{{ $r['customer'] ?: '-' }}</td><td>{{ $r['invoice'] ?: '-' }}</td><td>{{ RoutePerformanceReportService::paymentMethodLabel($r['method']) }}</td><td class="num">{{ $money($r['amount']) }}</td></tr>
@empty<tr><td class="empty" colspan="6">لا توجد تحصيلات معتمدة مربوطة بهذا الخط.</td></tr>@endforelse
</tbody></table></section>

<section class="section"><h3>مصاريف السيارات</h3>
<table><thead><tr><th>المصروف</th><th>التاريخ</th><th>النوع</th><th>طريقة الدفع</th><th class="num">المبلغ</th></tr></thead><tbody>
@forelse($report['expenses'] as $r)<tr><td>{{ $r['number'] }}</td><td class="num">{{ $r['date'] }}</td><td>{{ $r['type'] }}</td><td>{{ RoutePerformanceReportService::paymentMethodLabel($r['method']) }}</td><td class="num">{{ $money($r['amount']) }}</td></tr>
@empty<tr><td class="empty" colspan="5">لا توجد مصاريف معتمدة مربوطة بهذا الخط.</td></tr>@endforelse
</tbody></table></section>

<section class="section"><h3>التحميلات والإغلاقات</h3>
<table><thead><tr><th>النوع</th><th>المستند</th><th>التاريخ</th><th class="num">الكمية / المتوقع</th><th class="num">التكلفة / الفعلي</th><th class="num">الفرق</th></tr></thead><tbody>
@foreach($report['loads'] as $r)<tr><td>تحميل</td><td>{{ $r['number'] }}</td><td class="num">{{ $r['date'] }}</td><td class="num">{{ $qty($r['quantity']) }}</td><td class="num">{{ $money($r['cost']) }}</td><td class="num">-</td></tr>@endforeach
@foreach($report['closings'] as $r)<tr><td>إغلاق يومي</td><td>{{ $r['number'] }}</td><td class="num">{{ $r['date'] }}</td><td class="num">{{ $money($r['expected']) }}</td><td class="num">{{ $money($r['actual']) }}</td><td class="num">{{ $money($r['difference']) }}</td></tr>@endforeach
@if($report['loads'] === [] && $report['closings'] === [])<tr><td class="empty" colspan="6">لا توجد تحميلات أو إغلاقات خلال الفترة.</td></tr>@endif
</tbody></table></section>

<footer><span>تمت الطباعة بواسطة: {{ $generatedBy ?: '-' }}</span><span>{{ now()->format('Y-m-d H:i') }}</span></footer>
</main>
</body>
</html>
