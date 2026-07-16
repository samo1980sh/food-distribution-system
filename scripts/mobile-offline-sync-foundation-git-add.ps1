$ErrorActionPreference = "Stop"

$paths = @(
    ".env.example",
    "app/Console/Commands/InitializeInventoryCosts.php",
    "app/Console/Commands/PruneMobileSyncChanges.php",
    "app/Exceptions/Api/OperationalApiException.php",
    "app/Http/Controllers/Api/V1/Operational/Concerns/HandlesOperationalWriteResponses.php",
    "app/Http/Controllers/Api/V1/Operational/MobileSyncController.php",
    "app/Http/Controllers/Api/V1/Operational/OperationalBootstrapController.php",
    "app/Http/Requests/Api/V1/Operational/MobileSyncPullRequest.php",
    "app/Http/Resources/Api/V1/Operational/ProductCategoryResource.php",
    "app/Http/Resources/Api/V1/Operational/UnitResource.php",
    "app/Models/MobileSyncChange.php",
    "app/Models/MobileSyncCheckpoint.php",
    "app/Models/MobileSyncState.php",
    "app/Observers/MobileSyncChangeObserver.php",
    "app/Providers/MobileOfflineSyncServiceProvider.php",
    "app/Services/Api/MobileBootstrapService.php",
    "app/Services/Api/MobileOfflineSyncService.php",
    "app/Services/Api/MobileOperationalService.php",
    "app/Services/Api/MobileSyncCascadeService.php",
    "app/Services/Api/MobileSyncChangeRecorder.php",
    "app/Services/Api/MobileSyncContextService.php",
    "app/Services/Api/MobileSyncScopeService.php",
    "app/Services/Sales/CustomerPaymentService.php",
    "app/Support/Api/MobileSyncEntityRegistry.php",
    "bootstrap/providers.php",
    "config/mobile_api.php",
    "database/migrations/2026_07_16_160000_create_mobile_offline_sync_tables.php",
    "docs/api/MOBILE_OFFLINE_SYNC_FOUNDATION_AR.md",
    "docs/api/MOBILE_OFFLINE_SYNC_FOUNDATION_INSTALL_AR.md",
    "docs/api/MOBILE_OFFLINE_SYNC_FOUNDATION_MANIFEST.txt",
    "docs/api/MOBILE_OPERATIONAL_READ_API_AR.md",
    "docs/api/MOBILE_OPERATIONAL_WRITE_API_PHASE1_AR.md",
    "docs/api/openapi.yaml",
    "routes/api.php",
    "routes/console.php",
    "scripts/mobile-offline-sync-foundation-git-add.ps1",
    "tests/Feature/Api/MobileOfflineSyncFoundationTest.php",
    "tests/Feature/Api/MobileOperationalWriteApiTest.php"
)

foreach ($path in $paths) {
    git add -- $path
}

Write-Host "Staged Mobile Offline Sync Foundation Phase 1 paths successfully."
Write-Host "No git add . was used."
git status --short
