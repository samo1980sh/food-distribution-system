# تركيب Mobile Operational Write API — Phase 1

## نقطة البداية

يجب أن يكون المشروع عند commit:

`c7e79eb Add mobile operational read API`

ويجب أن يكون `git status --short` نظيفاً قبل فك الحزمة.

## فك الحزمة

فك محتويات ZIP مباشرة داخل:

`C:\laragon\www\food-distribution-system`

وافق على دمج المجلدات واستبدال الملفات الموجودة. لا تنشئ مجلداً وسيطاً داخل المشروع.

## التثبيت

```powershell
cd C:\laragon\www\food-distribution-system

php artisan migrate
php artisan optimize:clear
```

لا تحتاج المرحلة إلى Seeder جديد ولا تغيّر RolePermissionMap.

## التحقق من المسارات

```powershell
php artisan route:list --path=api/v1/operational
```

يجب أن تظهر مسارات POST وPATCH وDELETE وإجراءات confirm/cancel/approve/reject/refresh-totals بجانب مسارات القراءة السابقة.

## الاختبارات

```powershell
php artisan test --filter=MobileOperationalWriteApiTest
php artisan test --filter=MobileOperationalReadApiTest
php artisan test
git diff --check
```

## ملاحظة رفع إيصال المصروف

للإنشاء يمكن استخدام `multipart/form-data` مباشرة. لتحديث ملف باستخدام عملاء لا يرسلون ملفات مع PATCH، أرسل POST مع الحقل:

```text
_method=PATCH
```

إلى مسار المصروف نفسه.

## تجهيز Git

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\mobile-operational-write-api-git-add.ps1

git diff --cached --check
git diff --cached --stat
git status --short
```

السكربت يستخدم مسارات صريحة ولا يستخدم `git add .`.

بعد نجاح المراجعة يكون اسم commit المقترح:

```text
Add mobile operational write API
```
