# تركيب Mobile Offline Sync Foundation — Phase 1

## نقطة البداية

```text
68ce548 Add mobile operational write API
```

يجب أن يكون `main` مطابقًا لـ`origin/main` وWorking Tree نظيفًا قبل فك الحزمة.

## التركيب

فك الحزمة مباشرة داخل جذر المشروع، ثم نفذ:

```powershell
php artisan migrate
php artisan optimize:clear
```

لا توجد صلاحيات جديدة ولا حاجة لإعادة تشغيل Seeder.

## جداول قاعدة البيانات الجديدة

- `mobile_sync_changes`
- `mobile_sync_checkpoints`
- `mobile_sync_states`

تقوم Migration بإنشاء Backfill للسجلات الحالية، لذلك قد يستغرق تنفيذها وقتًا أطول حسب حجم البيانات الفعلية.

## التحقق من المسارات

```powershell
php artisan route:list --path=api/v1/operational/sync
```

بعد تركيب Push Batch يصبح المتوقع ثلاثة مسارات:

```text
GET|HEAD api/v1/operational/sync/status
POST     api/v1/operational/sync/pull
POST     api/v1/operational/sync/push
```

## الاختبارات

```powershell
php artisan test --filter=MobileOfflineSyncFoundationTest
php artisan test --filter=MobileOperationalWriteApiTest
php artisan test --filter=MobileOperationalReadApiTest
php artisan test --filter=InitializeInventoryCostsCommandTest
php artisan test
git diff --check
git status --short
```

## إعدادات اختيارية

```dotenv
MOBILE_API_SYNC_DEFAULT_PULL_LIMIT=200
MOBILE_API_SYNC_MAX_PULL_LIMIT=500
MOBILE_API_SYNC_RETENTION_DAYS=90
MOBILE_API_SYNC_MAX_PUSH_OPERATIONS=50
MOBILE_API_SYNC_MAX_PUSH_OPERATION_KB=256
MOBILE_API_SYNC_PUSH_PROCESSING_TIMEOUT_SECONDS=300
```

## فحص أمر Compaction

شغّل Dry Run فقط أثناء المراجعة:

```powershell
php artisan mobile-sync:prune
```

لا تستخدم `--apply` يدويًا قبل نجاح الاختبارات ومراجعة الناتج. توجد جدولة تلقائية يومية بعد اعتماد المرحلة.

## Git

بعد نجاح الاختبارات والمراجعة فقط:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\mobile-offline-sync-foundation-git-add.ps1

git diff --cached --check
git diff --cached --stat
git status --short
```

السكربت لا يستخدم `git add .`.
