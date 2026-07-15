# تركيب نظام RBAC الاحترافي

هذه الوثيقة تخص حزمة RBAC Foundation بعد تثبيت جميع إصلاحات Authorization النهائية.

## المتطلبات

- نقطة البداية: commit `8a268af`
- Laravel `12.62.0`
- Filament `~5.0`
- PHP `8.2+`
- قاعدة بيانات تطوير احتياطية قبل الترحيل

## تثبيت الاعتماد دون تحديث Laravel

```powershell
composer update spatie/laravel-permission `
    --with-dependencies `
    --minimal-changes `
    --no-scripts

php artisan package:discover --ansi
php artisan optimize:clear
```

الإصدار الذي تم اختباره محليًا:

```text
spatie/laravel-permission 6.25.0
laravel/framework 12.62.0
```

## فحص ما قبل الترحيل

تأكد من وجود Super Admin فعّال، وعدم وجود أدوار غير معروفة أو روابط موظفين مكررة.

## تشغيل الترحيل والـSeeder

```powershell
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan permission:cache-reset
php artisan optimize:clear
```

النتيجة المتوقعة:

- 7 أدوار.
- 105 صلاحيات.
- حذف عمود `users.role` القديم بعد ترحيل المستخدمين.
- كل مستخدم حالي يحتفظ بدوره المقابل.
- قيد unique على `employees.user_id`.

## الاختبارات

```powershell
php artisan test --filter=RbacFoundationTest
php artisan test
```

النتيجة التي تم التحقق منها أثناء التطوير:

```text
RbacFoundationTest: 8 passed, 21 assertions
Full suite: 70 passed, 295 assertions
```

## فحص ما قبل Git

```powershell
git diff --check
composer validate --no-check-publish
php artisan migrate:status
```

ثم استخدم:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\rbac-git-add.ps1
```

السكربت يضيف المسارات المعتمدة فقط، ولا يستخدم `git add .`.

## ملاحظات مهمة

- لا تضف `vendor` أو `.env` أو ملفات ZIP إلى Git.
- لا تشغّل `filament:upgrade` ضمن هذا التغيير؛ لا توجد حاجة لتحديث أصول Filament.
- لا تستخدم `composer update --with-all-dependencies` لأنه يحدّث Laravel وحزمًا أخرى خارج نطاق RBAC.
- السائق والمندوب مجهزان كأدوار، لكنهما لا يدخلان لوحة Filament حاليًا؛ سيتم استخدامهما لاحقًا عبر API وتطبيق Flutter.
- طبقة تقييد البيانات حسب المنطقة والخط والمستودع والسيارة ستنفذ في المرحلة التالية.

## التراجع

لأن الترحيل ينقل الأدوار ويحذف عمود `users.role` القديم، فإن الطريقة الأكثر أمانًا للتراجع الكامل أثناء التطوير هي:

1. الرجوع إلى commit السابق.
2. استعادة نسخة قاعدة البيانات التي أُخذت قبل تشغيل Migration.

لا تعتمد على rollback وحده في بيئة تحتوي بيانات مهمة دون نسخة احتياطية.
