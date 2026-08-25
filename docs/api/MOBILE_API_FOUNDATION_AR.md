# أساس Mobile API

## الهدف

يوفر النظام API رسميًا لتطبيق Flutter تحت المسار:

```text
/api/v1
```

يعيد الـAPI استخدام Laravel Sanctum وRBAC ونطاقات الوصول نفسها المستخدمة في لوحة الإدارة. الدور الميداني الوحيد المدعوم هو `sales_representative`.

## المصادقة والجلسات

| Method | Endpoint | الوصف |
|---|---|---|
| GET | `/api/v1/health` | فحص جاهزية التطبيق وقاعدة البيانات. |
| POST | `/api/v1/auth/login` | تسجيل الدخول وإصدار رمز خاص بالجهاز. |
| GET | `/api/v1/auth/me` | المستخدم والصلاحيات والنطاق والخصائص المتاحة. |
| GET | `/api/v1/auth/sessions` | جلسات التطبيق الحالية. |
| DELETE | `/api/v1/auth/sessions/{token}` | إنهاء جلسة يملكها المستخدم. |
| POST | `/api/v1/auth/logout` | إنهاء جلسة الجهاز الحالي. |
| POST | `/api/v1/auth/logout-all` | إنهاء جميع جلسات التطبيق. |

مثال تسجيل الدخول:

```json
{
  "email": "sales@example.com",
  "password": "secret",
  "device_id": "8f8597fd-bbbb-4444-9999-9ad6c8ac64fa",
  "device_name": "Samsung A55",
  "platform": "android",
  "app_version": "1.0.0"
}
```

## شكل الاستجابة

```json
{
  "success": true,
  "message": "تمت العملية بنجاح.",
  "data": {}
}
```

أخطاء التحقق تستخدم `success=false` و`code=validation_failed` مع كائن `errors`. أخطاء التعارض التشغيلي تستخدم HTTP 409 ورمزًا ثابتًا يمكن للتطبيق معالجته.

## الأمان

- يرسل التطبيق `Authorization: Bearer <token>` و`Accept: application/json`.
- الرمز يحمل ability باسم `api:v1`.
- الحساب يجب أن يكون فعالًا ويملك `api.access` ودور `sales_representative`.
- كل رمز مرتبط بـ`device_id`، مع حد للجلسات ومدة صلاحية قابلة للضبط.
- Policies وData Scopes تبقى المرجع النهائي لصلاحيات السجل والنطاق.
- لا يخزن تطبيق Flutter كلمة المرور أو الرمز كنص صريح.

## العقود التشغيلية

- OpenAPI: `docs/api/openapi.yaml`.
- قراءة التشغيل: `MOBILE_OPERATIONAL_READ_API_AR.md`.
- الكتابة: `MOBILE_OPERATIONAL_WRITE_API_PHASE1_AR.md`.
- سياق اليوم: `MOBILE_FIELD_TODAY_READ_AR.md`.
- الإغلاق الميداني: `MOBILE_DAILY_CLOSING_PHASE3D1_SYNC_CONTRACT_AR.md`.

دورة العمل الحالية موحدة: أمر تحميل، `SalesJourney`، زيارات، فواتير بتسليم فوري، تحصيلات، مرتجعات، مصاريف، إنهاء الجولة، جرد السيارة، تسليم النقد، ثم الإغلاق اليومي.
