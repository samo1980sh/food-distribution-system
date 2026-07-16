$ErrorActionPreference = "Stop"

$paths = @(
    ".env.example",
    "app/Http/Controllers/Api/V1/Operational/MobileSyncController.php",
    "app/Http/Controllers/Api/V1/Operational/OperationalBootstrapController.php",
    "app/Http/Requests/Api/V1/Operational/MobileSyncPushRequest.php",
    "app/Models/MobileSyncPushBatch.php",
    "app/Models/MobileSyncPushOperation.php",
    "app/Services/Api/MobileOfflineSyncService.php",
    "app/Services/Api/MobileSyncPushOperationService.php",
    "app/Services/Api/MobileSyncPushRequestValidator.php",
    "app/Services/Api/MobileSyncPushService.php",
    "app/Services/Api/MobileSyncVersionService.php",
    "app/Support/Api/MobileSyncEntityRegistry.php",
    "app/Support/Api/MobileSyncPushRegistry.php",
    "config/mobile_api.php",
    "database/migrations/2026_07_16_190000_create_mobile_sync_push_tables.php",
    "docs/api/MOBILE_OFFLINE_SYNC_FOUNDATION_AR.md",
    "docs/api/MOBILE_OFFLINE_SYNC_FOUNDATION_INSTALL_AR.md",
    "docs/api/MOBILE_OFFLINE_SYNC_FOUNDATION_MANIFEST.txt",
    "docs/api/MOBILE_OFFLINE_SYNC_PUSH_BATCH_AR.md",
    "docs/api/MOBILE_OFFLINE_SYNC_PUSH_BATCH_INSTALL_AR.md",
    "docs/api/MOBILE_OFFLINE_SYNC_PUSH_BATCH_MANIFEST.txt",
    "docs/api/MOBILE_OPERATIONAL_WRITE_API_PHASE1_AR.md",
    "docs/api/openapi.yaml",
    "routes/api.php",
    "scripts/mobile-offline-sync-push-batch-git-add.ps1",
    "tests/Feature/Api/MobileOfflineSyncFoundationTest.php",
    "tests/Feature/Api/MobileOfflineSyncPushBatchTest.php"
)

foreach ($path in $paths) {
    git add -- $path
}

Write-Host "Staged Mobile Offline Sync Push Batch Phase 2 paths successfully."
Write-Host "No git add . was used."
git status --short
