$ErrorActionPreference = "Stop"

$project = Split-Path -Parent $PSScriptRoot
Set-Location $project

php artisan optimize:clear
php artisan test --filter=OperationalInventoryImpactTest
php artisan test

git diff --check
git status --short
