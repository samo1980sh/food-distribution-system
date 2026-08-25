# تركيب Mobile API

## الإعداد

راجع القيم التالية في ملف البيئة المناسب دون وضع أسرار داخل Git:

```text
MOBILE_API_VERSION=v1
MOBILE_API_TOKEN_TTL_MINUTES=43200
MOBILE_API_MAX_SESSIONS=5
MOBILE_API_TOKEN_TOUCH_INTERVAL=300
MOBILE_API_RATE_LIMIT=120
MOBILE_API_LOGIN_RATE_LIMIT=5
```

يجب أن تكون Sanctum وSpatie Permission مهيأتين، وأن تكون صلاحية `api.access` موجودة لدور `sales_representative` وفق `RolePermissionMap`.

## التحقق

```powershell
php artisan route:list --path=api/v1
php artisan test --filter=MobileApiFoundationTest
php artisan test --filter=MobileAppAccessTest
```

تحقق من ظهور:

- `/api/v1/health`
- `/api/v1/auth/login`
- `/api/v1/auth/me`
- `/api/v1/operational/bootstrap`
- `/api/v1/operational/today`
- `/api/v1/operational/sync/pull`
- `/api/v1/operational/sync/push`

## تجربة تسجيل الدخول

```powershell
$body = @{
  email = "sales@example.com"
  password = "secret"
  device_id = "local-test-device"
  device_name = "Local browser"
  platform = "android"
  app_version = "1.0.0"
} | ConvertTo-Json

Invoke-RestMethod -Method Post `
  -Uri "http://localhost/api/v1/auth/login" `
  -ContentType "application/json" `
  -Body $body
```

استخدم عنوان المشروع الفعلي في البيئة المحلية. لا تضع token أو بيانات الحسابات الحقيقية في scripts أو commits.

## تشخيص الرفض

- `401`: بيانات الدخول أو الرمز غير صالحين.
- `403 account_inactive`: الحساب غير فعال.
- `403 api_access_denied`: صلاحية API غير موجودة.
- `403 mobile_role_denied`: الحساب لا يمثل مندوب المبيعات الميداني.
- `429`: تجاوز rate limit.

مرجع العقود التفصيلي هو `docs/api/openapi.yaml`، ولا تتطلب هذه الطبقة أي runtime ميداني موازٍ لمسار المندوب الموحد.
