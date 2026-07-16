# تركيب Mobile Offline Sync Push Batch + Conflict Handling — Phase 2

## نقطة البداية

```text
5761a38 Add mobile offline sync foundation
```

يجب أن يكون `main` مطابقًا لـ`origin/main` وWorking Tree نظيفًا.

## التركيب

فك الحزمة داخل جذر المشروع ثم نفذ:

```powershell
php artisan migrate
php artisan optimize:clear
php artisan route:list --path=api/v1/operational/sync
```

المتوقع ثلاثة مسارات: status وpull وpush، ويصبح إجمالي مسارات `/api/v1/operational` هو 54 مسارًا.

## الجداول الجديدة

- `mobile_sync_push_batches`
- `mobile_sync_push_operations`

## الاختبارات

```powershell
php artisan test --filter=MobileOfflineSyncPushBatchTest
php artisan test --filter=MobileOfflineSyncFoundationTest
php artisan test --filter=MobileOperationalWriteApiTest
php artisan test --filter=MobileOperationalReadApiTest
php artisan test
git diff --check
git status --short
```

## Git

بعد نجاح الاختبارات والمراجعة فقط:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\mobile-offline-sync-push-batch-git-add.ps1

git diff --cached --check
git diff --cached --stat
git status --short
```

السكربت لا يستخدم `git add .`.
