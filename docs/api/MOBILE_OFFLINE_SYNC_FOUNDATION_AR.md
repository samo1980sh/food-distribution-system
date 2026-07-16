# Mobile Offline Sync Foundation — Phase 1

## الهدف

تضيف هذه المرحلة أساس مزامنة احترافيًا لتطبيق Flutter فوق Mobile Operational Read/Write API، مع الحفاظ على RBAC وData Scopes وقواعد الأعمال الحالية كمصدر وحيد للصلاحيات.

## ما تم دعمه

- سجل تغييرات مركزي ومتزايد باستخدام Cursor رقمي دائم.
- Pull أولي وتدريجي لجميع الكيانات المسموح بها للمستخدم.
- Tombstones للحذف حتى يستطيع التطبيق حذف السجل محليًا.
- التقاط تغييرات Eloquent داخل نفس Transaction الخاصة بالعملية.
- التقاط آثار `cascadeOnDelete` و`nullOnDelete` للكيانات المتزامنة.
- حماية كاملة بواسطة الصلاحيات ونطاق الوصول الفعلي.
- Context Key يتغير عند تغير الدور أو الصلاحيات أو نطاق الوصول.
- إلزام التطبيق بمزامنة كاملة عند تغير Context Key.
- حالة مزامنة مستقلة لكل مستخدم وجهاز.
- Backfill تلقائي للسجلات الموجودة عند تشغيل Migration.
- Compaction آمن يحتفظ بآخر حالة لازمة لكل سجل بدل حذف السجل زمنيًا بشكل أعمى.
- Watermark يكتشف الأجهزة ذات Cursor القديم ويطلب منها Full Reset.
- استمرار Push عبر مسارات الكتابة الحالية مع `client_reference` لمنع تكرار الإنشاء.

## المسارات الجديدة

```text
GET  /api/v1/operational/sync/status
POST /api/v1/operational/sync/pull
```

## دورة المزامنة

1. يسجل التطبيق الدخول ويحصل على Device-bound Token.
2. يقرأ `operational/bootstrap` أو `sync/status` ويحفظ `context_key`.
3. يرسل `cursor = 0` لتنفيذ Full Pull.
4. يطبق التغييرات بالترتيب التصاعدي حسب Cursor.
5. يحفظ `data.cursor` بعد نجاح Transaction المحلية.
6. يكرر الطلب بينما `has_more = true`.
7. في المرات التالية يرسل آخر Cursor محفوظ مع نفس `context_key`.

الطلب الأول:

```json
{
  "cursor": 0,
  "limit": 200,
  "context_key": "64-character-context-key"
}
```

عند استخدام Cursor أكبر من صفر يصبح `context_key` إلزاميًا، ويجب أن يكون SHA-256 hex بطول 64 حرفًا.

## شكل التغيير

```json
{
  "cursor": 120,
  "entity": "sales_invoices",
  "operation": "upsert",
  "record_id": 44,
  "version": "2026-07-16T14:00:00+03:00",
  "record": {},
  "changed_at": "2026-07-16T14:00:01+03:00"
}
```

وعند الحذف:

```json
{
  "cursor": 121,
  "entity": "sales_invoices",
  "operation": "delete",
  "record_id": 44,
  "version": null,
  "record": null,
  "changed_at": "2026-07-16T14:01:00+03:00"
}
```

يجب أن يخزن التطبيق البيانات بصورة Normalized حسب `entity + record_id`. الكائنات المتداخلة داخل Resources هي ملخصات مساعدة، أما جداول الكيانات المستقلة فهي المرجع المحلي الأساسي.

## Context Key وإعادة الضبط

يتغير Context Key عندما يتغير:

- الدور.
- الصلاحيات الفعلية.
- المناطق أو الخطوط أو المركبات أو المستودعات أو الموظفون ضمن النطاق.
- إصدار سجل كيانات المزامنة.

عند تغير السياق تعيد الخدمة:

```json
{
  "success": false,
  "code": "sync_context_changed",
  "errors": {
    "sync": {
      "reset_required": true,
      "context_key": "new-key",
      "cursor": 0
    }
  }
}
```

يجب مسح قاعدة البيانات المحلية الخاصة بالحساب ثم تنفيذ Pull من Cursor صفر.

## انتهاء Cursor وCompaction

يتم ضغط سجل التغييرات دوريًا مع الاحتفاظ بآخر Upsert لكل سجل موجود، والاحتفاظ بالتغييرات الحديثة وTombstones ضمن مدة الاحتفاظ. يسجل النظام Watermark باسم `minimum_cursor`.

إذا كان Cursor الجهاز أقدم من Watermark تعيد الخدمة:

```text
code: sync_cursor_expired
errors.sync.reset_required: true
errors.sync.cursor: 0
```

حتى بعد Compaction يبقى Full Pull من Cursor صفر قادرًا على إعادة بناء الحالة الحالية كاملة.

## الكتابة أثناء Offline

في هذه المرحلة يخزن تطبيق Flutter عمليات الكتابة محليًا ويرسلها بالتسلسل عند عودة الاتصال عبر مسارات الكتابة الحالية.

```text
push_mode: rest_idempotent
batch_push_supported: false
```

إنشاء المستندات آمن لإعادة المحاولة بواسطة `client_reference`. Batch Push وحل تعارضات التعديل المتقدمة مؤجلان لمرحلة لاحقة.

## الكيانات المشمولة

- areas
- routes
- vehicles
- warehouses
- employees
- product_categories
- units
- products
- customers
- stock_balances
- vehicle_loads
- sales_invoices
- customer_payments
- sales_returns
- vehicle_expenses
- daily_closings

البيانات المرجعية اللازمة للكتالوج والخطوط، مثل التصنيفات والوحدات وملخصات الموظفين والمناطق، تُرسل لمن يملك صلاحية الوحدة التشغيلية التي تحتاجها، مع استمرار تطبيق Data Scopes.

## الحذف المتسلسل

عندما يؤدي حذف سجل رئيسي إلى حذف سجلات أخرى أو تصفير مفاتيحها الأجنبية داخل قاعدة البيانات، يسجل النظام:

- Tombstone للسجلات المحذوفة بـCascade.
- Upsert جديد للسجلات الباقية التي أصبحت علاقاتها `null`.

يشمل ذلك أمثلة مثل حذف منطقة أو خط أو مركبة أو مستودع أو عميل أو منتج.

## الاحتفاظ والضغط

القيمة الافتراضية 90 يومًا:

```dotenv
MOBILE_API_SYNC_RETENTION_DAYS=90
```

المعاينة دون حذف:

```powershell
php artisan mobile-sync:prune
```

التطبيق الفعلي للـCompaction الآمن:

```powershell
php artisan mobile-sync:prune --apply
```

تمت إضافة جدولة يومية تلقائية الساعة 02:30.
