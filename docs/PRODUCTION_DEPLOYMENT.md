# Production Deployment Runbook

هذا الملف هو مرجع نشر وتشغيل **FreshRoute / Food Distribution System** على بيئة الإنتاج.

## 1. متطلبات الخادم

- PHP 8.3 أو إصدار متوافق مع `composer.lock`.
- MySQL / MariaDB متوافق مع المشروع.
- Composer 2.
- HTTPS إلزامي.
- Document Root يجب أن يشير إلى مجلد `public/` فقط.
- يجب أن تكون المجلدات `storage/` و `bootstrap/cache/` قابلة للكتابة من مستخدم PHP.

## 2. النسخ الاحتياطي قبل كل نشر

قبل استبدال الكود أو تشغيل migrations:

1. خذ نسخة كاملة من قاعدة البيانات.
2. خذ نسخة من `storage/app/private/`، وخصوصًا إيصالات مصاريف المركبات.
3. احتفظ بنسخة من ملف `.env` الإنتاجي خارج حزمة الكود.

لا تستبدل ملف `.env` الموجود أثناء تحديث عادي.

## 3. إعداد أول نشر فقط

ابدأ من `.env.production.example` وأنشئ `.env` حقيقيًا على الخادم، ثم اضبط القيم الفعلية:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
DB_CONNECTION=mysql
DB_HOST=...
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...
SESSION_SECURE_COOKIE=true
```

أنشئ `APP_KEY` مرة واحدة فقط في أول نشر:

```bash
php artisan key:generate
```

يجب الحفاظ على نفس `APP_KEY` في كل تحديث لاحق، لأن تغييره يبطل البيانات المشفرة والجلسات.

## 4. خطوات النشر / التحديث

من جذر المشروع:

```bash
php artisan down

composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

php artisan migrate --force
php artisan db:seed --force

php artisan optimize:clear
php artisan optimize

php artisan up
```

الـdefault seeder في هذا المشروع مخصص لتهيئة أدوار وصلاحيات النظام فقط، ولا ينشئ بيانات أعمال Demo.

## 5. Scheduler إلزامي

النظام يحتوي حاليًا على مهمتين مجدولتين:

- `sanctum:prune-expired --hours=24`
- `mobile-sync:prune --apply`

يجب تشغيل Laravel Scheduler كل دقيقة من Cron / cPanel:

```cron
* * * * * cd /path/to/food-distribution-system && php artisan schedule:run >> /dev/null 2>&1
```

تحقق بعد النشر:

```bash
php artisan schedule:list
```

## 6. Queue

إعداد المشروع الحالي يستخدم `QUEUE_CONNECTION=database`، لكن التشغيل المدقق حاليًا لا يعتمد على Queue Worker كخدمة إلزامية.

إذا أضيفت لاحقًا Jobs أو Listeners من نوع queued، يجب عندها تشغيل وإدارة:

```bash
php artisan queue:work --sleep=3 --tries=3
```

ولا يُضاف Worker إلى الإنتاج قبل وجود مهام Queue فعلية ومراقبتها.

## 7. التخزين والإيصالات

إيصالات مصاريف المركبات الجديدة مخزنة في التخزين الخاص `storage/app/private` وتُعرض فقط من خلال Laravel بعد التحقق من المصادقة والصلاحيات ونطاق البيانات.

لذلك **لا تنشئ `public/storage` من أجل إيصالات المصاريف**.

استخدم:

```bash
php artisan storage:link
```

فقط إذا كان هناك Feature عام آخر تم التحقق منه ويحتاج ملفات على `public` disk.

## 8. التحقق بعد النشر

نفّذ:

```bash
php artisan about
php artisan migrate:status
php artisan schedule:list
php artisan route:list --path=api/v1
```

ثم تحقق عمليًا من:

- `/` يحول إلى `/admin`.
- `/admin/login` يعمل عبر HTTPS.
- `/up` يعيد Health Response ناجحًا.
- `/api/v1/health` يعيد عقد API الصحيح.
- تسجيل دخول Flutter يعمل.
- فتح إيصال مصروف المركبة يعمل للمستخدم المخول ولا يعمل بدون مصادقة.
- `APP_ENV=production`.
- `APP_DEBUG=false`.

## 9. Logs

راجع:

```text
storage/logs/
```

لا تعرض ملفات Log للعامة ولا تضعها تحت `public/`.

في حال وجود خطأ API غير متوقع، يرجع التطبيق للموبايل رسالة عامة بدون تفاصيل داخلية، بينما تبقى تفاصيل الخطأ متاحة في Logs الخادم.

## 10. Rollback

قبل rollback تأكد من وجود نسخة قاعدة بيانات حديثة.

الترتيب الآمن:

```bash
php artisan down
```

ثم أعد نسخة الكود السابقة، واسترجع قاعدة البيانات من Backup إذا كان النشر الذي يتم التراجع عنه قد شغّل migrations غير متوافقة مع النسخة السابقة.

بعد استعادة الكود والبيانات:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan optimize:clear
php artisan optimize
php artisan up
```

لا تستخدم `migrate:rollback` بشكل أعمى على قاعدة إنتاج تحتوي عمليات فعلية.

## 11. نقاط أمان أساسية

- HTTPS فقط.
- `APP_DEBUG=false`.
- لا ترفع `.env` إلى Git أو إلى مساحة عامة.
- لا تستخدم حساب MySQL بصلاحيات أوسع من الحاجة.
- خذ نسخًا احتياطية دورية لقاعدة البيانات و`storage/app/private`.
- اختبر استعادة النسخة الاحتياطية دوريًا، وليس إنشاءها فقط.
- راقب مساحة القرص وملفات Logs.
- نفّذ `composer audit` قبل كل Release.
