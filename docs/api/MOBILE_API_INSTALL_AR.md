# تركيب Mobile API Foundation

## نقطة البداية

```text
8f014c6 Add role-based data access scopes
```

يجب أن يكون `git status --short` فارغًا قبل فك الحزمة.

## متغيرات البيئة

أضيفت القيم التالية إلى `.env.example`. يمكن ترك القيم الافتراضية أو إضافتها إلى `.env`:

```dotenv
MOBILE_API_VERSION=v1
MOBILE_API_TOKEN_TTL_MINUTES=43200
MOBILE_API_MAX_SESSIONS=5
MOBILE_API_TOKEN_TOUCH_INTERVAL=300
MOBILE_API_RATE_LIMIT=120
MOBILE_API_LOGIN_RATE_LIMIT=5
```

## بعد فك ZIP

```powershell
cd C:\laragon\www\food-distribution-system

php artisan optimize:clear
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan permission:cache-reset
php artisan optimize:clear
```

تشغيل Seeder مطلوب لإضافة صلاحية:

```text
api.access
```

إلى مصفوفة الأدوار.

## فحص النتيجة

```powershell
php artisan migrate:status
php artisan route:list --path=api/v1

php artisan tinker --execute="dump([
    'api_permission' => \Spatie\Permission\Models\Permission::where('name', 'api.access')->exists(),
    'permissions_count' => \Spatie\Permission\Models\Permission::count(),
    'mobile_columns' => [
        'device_id' => \Illuminate\Support\Facades\Schema::hasColumn('personal_access_tokens', 'device_id'),
        'last_seen_at' => \Illuminate\Support\Facades\Schema::hasColumn('personal_access_tokens', 'last_seen_at'),
    ],
]);"
```

المتوقع:

- Migration `2026_07_15_180000_add_mobile_metadata_to_personal_access_tokens` بحالة `Ran`.
- وجود `api.access`.
- إجمالي الصلاحيات `106`.
- أعمدة الجهاز موجودة.

## تشغيل Laravel Scheduler

لتنفيذ تنظيف الرموز المنتهية والمهام المجدولة، يجب أن يكون `php artisan schedule:run` مضافًا إلى Cron مرة كل دقيقة في بيئة الإنتاج.

## الاختبارات

```powershell
php artisan test --filter=MobileApiFoundationTest
php artisan test
```

ثم اختبار يدوي:

```powershell
$body = @{
    email = "driver@example.com"
    password = "password"
    device_id = "manual-test-device-0001"
    device_name = "PowerShell Test"
    platform = "android"
    app_version = "1.0.0"
} | ConvertTo-Json

$response = Invoke-RestMethod `
    -Method Post `
    -Uri "http://localhost/food-distribution-system/api/v1/auth/login" `
    -ContentType "application/json" `
    -Headers @{ Accept = "application/json" } `
    -Body $body

$token = $response.data.token

Invoke-RestMethod `
    -Method Get `
    -Uri "http://localhost/food-distribution-system/api/v1/auth/me" `
    -Headers @{
        Accept = "application/json"
        Authorization = "Bearer $token"
    }
```

عدّل عنوان المشروع والبريد وكلمة المرور بحسب بيئتك.

## Git

بعد نجاح الاختبارات:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\mobile-api-git-add.ps1

git status --short
git diff --cached --check
git diff --cached --stat
```

لا تستخدم `git add .`.


## Operational Read API

راجع `docs/api/MOBILE_OPERATIONAL_READ_API_AR.md` للمرحلة الأولى من بيانات التشغيل.
