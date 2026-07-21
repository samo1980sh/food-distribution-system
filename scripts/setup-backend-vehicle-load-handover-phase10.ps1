$ErrorActionPreference = "Stop"

$project = Split-Path -Parent $PSScriptRoot
Set-Location $project

php artisan migrate
php artisan optimize:clear
php artisan test --filter=VehicleLoadHandoverContractTest
php artisan test
git diff --check
git status --short
