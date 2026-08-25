# قراءة مساحة عمل المندوب لليوم

## المسار

```text
GET /api/v1/operational/today
```

هذا endpoint للقراءة فقط. لا ينشئ جولة أو زيارة ولا يعدل البيانات.

## الوصول

يستخدم `auth:sanctum` و`api.access` وtoken touch وrate limiting. الدور الميداني الوحيد هو `sales_representative`.

المعامل الاختياري:

```text
route_id=<active route id>
```

## اختيار خط اليوم

- يبحث فقط في الخطوط الفعالة المعينة للموظف المرتبط بالحساب.
- يطبق Data Scopes قبل اختيار الخط.
- `visit_days` الفارغة تعامل كجدول يومي.
- خط واحد مجدول يعيد `ready`.
- أكثر من خط يعيد `route_selection_required` مع الخطوط المرشحة.
- عدم وجود خط مجدول يعيد `not_scheduled_today`.
- `route_id` خارج النطاق يعاد كـ404.

## السياق المعاد

- الخط والمنطقة والسيارة ومستودع السيارة والمندوب.
- readiness وأسباب عدم الجاهزية.
- `SalesJourney` الحالية وحالتها.
- حالة استلام أوامر التحميل.
- زيارات العملاء وترتيبها وحالاتها.
- ملخص مخزون السيارة دون حقول تكلفة غير مصرح بها.
- ملخص الفواتير والتحصيلات والمرتجعات والمصاريف.
- حالة الجرد والنقد والإغلاق اليومي.

الحالات الرئيسية:

```text
ready
no_assignment
not_scheduled_today
route_selection_required
incomplete_assignment
```

## دورة الكتابة المرتبطة

القراءة تعرض snapshot فقط. فتح الجولة يتم عبر `sales-journeys/open-today`، ثم start/visit operations/finish. الفاتورة النهائية تؤكد فورًا وتخصم مخزون السيارة وفق قواعد Cash/Credit/Partial الحالية.

المزامنة الدورية تستخدم sync pull، بينما `today` مناسب لبناء واجهة اليوم والتحقق من readiness بعد المزامنة أو عند الاتصال.
