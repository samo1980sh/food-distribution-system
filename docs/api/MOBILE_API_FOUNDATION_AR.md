# Mobile API Foundation

## الهدف

هذه المرحلة تؤسس واجهة API معيارية تحت المسار:

```text
/api/v1
```

لتكون المصدر الرسمي لتطبيق Flutter مستقبلًا، مع إعادة استخدام نظام RBAC وData Scopes الحاليين بدل تكرار قواعد الأمان داخل التطبيق.

## ما تم تنفيذه

- مصادقة Bearer Token باستخدام Laravel Sanctum.
- صلاحية مستقلة باسم `api.access` لجميع الأدوار المعتمدة.
- رفض الحساب غير الفعّال وإبطال جميع جلساته فور تعطيله أو تغيير كلمة مروره.
- ربط كل رمز بجهاز محدد عبر `device_id`.
- تدوير الرمز تلقائيًا عند تسجيل الدخول من الجهاز نفسه.
- مدة صلاحية قابلة للضبط، افتراضيًا 30 يومًا.
- تنظيف يومي مجدول للرموز المنتهية عبر `sanctum:prune-expired`.
- حد أقصى للجلسات النشطة، افتراضيًا 5 أجهزة.
- عرض جلسات المستخدم وإنهاء جلسة محددة أو جميع الجلسات.
- Rate Limiting مستقل لتسجيل الدخول وطلبات API.
- استجابات JSON موحدة للأخطاء والنجاح.
- بيانات Bootstrap تتضمن المستخدم والدور والصلاحيات والنطاق الفعلي.
- ترويسة `X-API-Version` على استجابات `/api/v1`.
- OpenAPI specification داخل `docs/api/openapi.yaml`.

## المسارات

### عامة

| Method | Endpoint | الوصف |
|---|---|---|
| GET | `/api/v1/health` | فحص جاهزية API وقاعدة البيانات. |
| POST | `/api/v1/auth/login` | تسجيل الدخول وإصدار رمز الجهاز. |

### محمية

| Method | Endpoint | الوصف |
|---|---|---|
| GET | `/api/v1/auth/me` | المستخدم والصلاحيات والنطاق وبيانات التهيئة. |
| GET | `/api/v1/auth/sessions` | جلسات التطبيق الحالية للمستخدم. |
| DELETE | `/api/v1/auth/sessions/{token}` | إنهاء جلسة محددة تخص المستخدم نفسه. |
| POST | `/api/v1/auth/logout` | إنهاء جلسة الجهاز الحالي. |
| POST | `/api/v1/auth/logout-all` | إنهاء جميع جلسات التطبيق. |

## نموذج تسجيل الدخول

```json
{
  "email": "driver@example.com",
  "password": "secret",
  "device_id": "8f8597fd-bbbb-4444-9999-9ad6c8ac64fa",
  "device_name": "Samsung A55",
  "platform": "android",
  "app_version": "1.0.0"
}
```

`platform` يقبل حاليًا `android` أو `ios` فقط.

## شكل الاستجابة الناجحة

```json
{
  "success": true,
  "message": "تمت العملية بنجاح.",
  "data": {}
}
```

## شكل الخطأ

```json
{
  "success": false,
  "message": "تعذر قبول البيانات المرسلة.",
  "code": "validation_failed",
  "errors": {
    "email": ["بيانات تسجيل الدخول غير صحيحة."]
  }
}
```

## الأمان

- التطبيق يرسل الرمز في الترويسة:

```text
Authorization: Bearer <token>
Accept: application/json
```

- الرمز يحمل ability باسم `api:v1`.
- Middleware يتحقق من أن الحساب فعّال ويملك `api.access`.
- صلاحيات الموارد تظل خاضعة لـSpatie Permissions وLaravel Policies.
- نطاقات المنطقة والخط والسيارة والمستودع تُستخرج من `AccessScopeService` نفسه المستخدم في لوحة الإدارة.
- لا تُخزّن كلمة المرور أو الرمز النصي في Flutter كنص صريح؛ يستخدم Flutter Secure Storage لاحقًا.

## حدود هذه المرحلة

هذه الحزمة تؤسس المصادقة والجلسات والـBootstrap فقط. لم تُفتح بعد endpoints تشغيلية للفواتير والتحصيل والمرتجعات والمصاريف والمزامنة دون اتصال. ستضاف تلك الوحدات على مراحل مستقلة بعد اعتماد هذا الأساس.
