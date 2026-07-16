# Mobile Offline Sync Push Batch + Conflict Handling — Phase 2

## الهدف

تمكّن هذه المرحلة تطبيق Flutter من رفع العمليات المخزنة محليًا دفعة واحدة بعد عودة الاتصال، مع منع التنفيذ المكرر وكشف التعارض قبل تعديل سجل أحدث على الخادم.

## المسار الجديد

```text
POST /api/v1/operational/sync/push
```

## الكيانات والعمليات

- `sales_invoices`: create, update, delete, confirm, cancel
- `customer_payments`: create, update, delete, confirm, cancel
- `sales_returns`: create, update, delete, confirm, cancel
- `vehicle_expenses`: create, update, delete, approve, reject
- `daily_closings`: create, update, delete, refresh_totals, confirm, cancel

## معرفات منع التكرار

- `batch_id`: معرف ثابت لمحاولة رفع قائمة العمليات كاملة.
- `operation_id`: معرف ثابت لكل عملية محلية، ويبقى ثابتًا عند إعادة المحاولة أو نقل العملية إلى دفعة جديدة.
- `client_reference`: يبقى إلزاميًا عند إنشاء المستندات التشغيلية.

إعادة نفس الدفعة بنفس المحتوى تعيد النتيجة المحفوظة ولا تنفذ العمليات مجددًا. استخدام المعرف نفسه مع محتوى مختلف يعيد تعارض Idempotency.

## حماية التعارض

كل عملية غير الإنشاء يجب أن ترسل:

```json
{
  "record_id": 15,
  "base_version": "c:982"
}
```

`base_version` هي قيمة `version` غير القابلة للتخمين بصيغة `c:<cursor>` التي استلمها التطبيق من Pull أو من نتيجة Push سابقة. لا تعتمد على وقت الجهاز أو `updated_at`. إذا كانت نسخة الخادم أحدث، تعاد نتيجة للعملية نفسها:

```json
{
  "status": "conflict",
  "code": "sync_version_conflict",
  "errors": {
    "conflict": {
      "base_version": "...",
      "current_version": "...",
      "resolution": "server_wins_pull_then_retry",
      "server_record": {}
    }
  }
}
```

لا يتم تعديل سجل الخادم. يعرض التطبيق التعارض للمستخدم أو يسحب النسخة الحالية ويعيد إنشاء عملية جديدة بمعرف جديد.

## النجاح الجزئي

تعالج العمليات بالترتيب وبشكل مستقل. فشل عملية أو تعارضها لا يمنع بقية الدفعة. الاستجابة HTTP 200 تعني أن الدفعة عولجت، ويجب فحص `data.results` لكل عملية:

- `applied`
- `replayed`
- `conflict`
- `failed`

## مثال طلب

```json
{
  "context_key": "64-character-context-key",
  "batch_id": "android-device-42:batch:1007",
  "operations": [
    {
      "operation_id": "android-device-42:op:5011",
      "entity": "sales_invoices",
      "action": "create",
      "payload": {
        "client_reference": "android-device-42:invoice:91",
        "customer_id": 5,
        "warehouse_id": 2,
        "invoice_date": "2026-07-16",
        "payment_type": "cash",
        "items": [
          { "product_id": 8, "quantity": 2, "unit_price": 10 }
        ]
      }
    }
  ]
}
```

## ملاحظات مهمة

- RBAC وData Scopes والـPolicies الحالية تطبق على كل عملية.
- السجل خارج النطاق يعاد كـ`http_404` داخل نتيجة العملية.
- قواعد الأعمال والمخزون والتحصيل والإغلاق يعاد استخدامها من الخدمات الحالية.
- رفع صورة إيصال مصروف السيارة غير مدعوم داخل JSON Batch. استخدم مسار Vehicle Expense REST المباشر للملف، أو أرسل المصروف دون صورة ثم ارفقها Online.
- عند تغير `context_key` ترفض الدفعة كاملة ويجب تنفيذ Full Reset/Pull.

## الحدود الافتراضية

```dotenv
MOBILE_API_SYNC_MAX_PUSH_OPERATIONS=50
MOBILE_API_SYNC_MAX_PUSH_OPERATION_KB=256
MOBILE_API_SYNC_PUSH_PROCESSING_TIMEOUT_SECONDS=300
```
