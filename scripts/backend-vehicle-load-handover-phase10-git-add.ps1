$ErrorActionPreference = "Stop"

$project = Split-Path -Parent $PSScriptRoot
Set-Location $project

git add -- `
  app/Filament/Resources/VehicleLoads/Actions/VehicleLoadActions.php `
  app/Http/Controllers/Api/V1/Operational/OperationalBootstrapController.php `
  app/Http/Controllers/Api/V1/Operational/VehicleLoadController.php `
  app/Http/Requests/Api/V1/Operational/VehicleLoadHandoverRequest.php `
  app/Http/Resources/Api/V1/Operational/VehicleLoadItemResource.php `
  app/Http/Resources/Api/V1/Operational/VehicleLoadResource.php `
  app/Models/VehicleLoad.php `
  app/Models/VehicleLoadItem.php `
  app/Policies/VehicleLoadPolicy.php `
  app/Services/Api/MobileOperationalService.php `
  app/Services/Api/MobileSyncPushOperationService.php `
  app/Filament/Resources/VehicleLoads/Schemas/VehicleLoadForm.php `
  app/Services/Distribution/VehicleLoadHandoverService.php `
  app/Services/Distribution/VehicleLoadService.php `
  app/Support/Api/MobileSyncEntityRegistry.php `
  app/Support/Api/MobileSyncPushRegistry.php `
  database/migrations/2026_07_21_140000_add_vehicle_load_handover_fields.php `
  docs/api/MOBILE_VEHICLE_LOAD_HANDOVER_PHASE10_AR.md `
  docs/api/MOBILE_VEHICLE_LOAD_HANDOVER_PHASE10_MANIFEST.txt `
  docs/api/VEHICLE_LOAD_FEFO_AUTOMATION_HOTFIX_AR.md `
  docs/api/VEHICLE_LOAD_POST_ACTION_REFRESH_HOTFIX_AR.md `
  routes/api.php `
  scripts/setup-backend-vehicle-load-handover-phase10.ps1 `
  scripts/setup-backend-vehicle-load-fefo-hotfix-phase10.ps1 `
  scripts/setup-backend-vehicle-load-post-action-refresh-hotfix-phase10.ps1 `
  scripts/backend-vehicle-load-handover-phase10-git-add.ps1 `
  tests/Feature/Api/VehicleLoadHandoverContractTest.php `
  tests/Feature/OperationalInventoryImpactTest.php `
  tests/Feature/VehicleLoadExpenseFilamentWorkflowTest.php `
  tests/Feature/Api/MobileOfflineSyncFoundationTest.php `
  tests/Feature/ReportSidebarNavigationTest.php

git diff --cached --check
git status --short
