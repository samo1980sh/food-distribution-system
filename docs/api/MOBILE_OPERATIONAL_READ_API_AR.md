# Mobile Operational Read API

## النطاق

تقع واجهات القراءة تحت:

```text
/api/v1/operational
```

ابدأ بـ`GET /bootstrap` لمعرفة modules والصلاحيات والسياق، واستخدم `GET /today` لمساحة عمل المندوب اليومية.

## الوحدات

- routes، vehicles، warehouses، products.
- customers وstock-balances.
- vehicle-loads.
- sales-journeys وsales-visits.
- sales-invoices وcustomer-payments وsales-returns.
- vehicle-expenses وdaily-closings.

القوائم تدعم pagination وفلاتر البحث والتاريخ والحالة ومعرفات العلاقات وفق OpenAPI. تفاصيل المستندات تعيد العناصر التابعة عند الحاجة.

## النطاق والأمان

- Model binding يخفي السجل خارج النطاق كـ404.
- مندوب المبيعات يرى فقط خطه وعملاءه وسيارته ومستودعها والعمليات المرتبطة بها.
- حقول التكلفة والربح لا تظهر إلا بصلاحيات التقارير المناسبة.
- الـResources تعيد `actions` محسوبة من حالة السجل وPolicy الحالية.

## المزامنة

للمزامنة الدورية استخدم:

```text
GET /api/v1/operational/sync/status
GET /api/v1/operational/sync/pull
POST /api/v1/operational/sync/push
```

يدعم pull cursor وtombstones. يجب حفظ `context_key` وregistry version وعدم دمج snapshots من نطاقات مختلفة.

## المراجع

- الكتابة: `MOBILE_OPERATIONAL_WRITE_API_PHASE1_AR.md`.
- Field Today: `MOBILE_FIELD_TODAY_READ_AR.md`.
- OpenAPI: `docs/api/openapi.yaml`.
