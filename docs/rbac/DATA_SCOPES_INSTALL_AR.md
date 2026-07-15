# تركيب مرحلة Role Data Scopes

## نقطة البداية المطلوبة

```text
e24247a Build professional RBAC foundation
```

يجب أن يكون `git status --short` فارغًا، وأن يكون `main` مطابقًا لـ`origin/main`.

## قبل النسخ

خذ نسخة احتياطية من قاعدة بيانات التطوير.

ثم فك ملف ZIP مباشرة داخل:

```text
C:\laragon\www\food-distribution-system
```

لا يوجد اعتماد Composer جديد في هذه المرحلة.

## فحص الملفات

```powershell
Test-Path .\app\Services\Authorization\AccessScopeService.php
Test-Path .\app\Providers\AccessScopeServiceProvider.php
Test-Path .\database\migrations\2026_07_15_150000_create_user_access_scope_tables.php
Test-Path .\tests\Feature\RoleDataScopeTest.php
Test-Path .\docs\rbac\DATA_SCOPES_AR.md
```

يجب أن تكون جميع النتائج `True`.

## فحص ما قبل Migration

```powershell
php artisan tinker --execute="dump([
    'area_scope_table_exists' => \Illuminate\Support\Facades\Schema::hasTable('user_area_scopes'),
    'route_scope_table_exists' => \Illuminate\Support\Facades\Schema::hasTable('user_route_scopes'),
    'vehicle_scope_table_exists' => \Illuminate\Support\Facades\Schema::hasTable('user_vehicle_scopes'),
    'warehouse_scope_table_exists' => \Illuminate\Support\Facades\Schema::hasTable('user_warehouse_scopes'),
    'supervisors' => \App\Models\User::role('supervisor')->count(),
    'warehouse_keepers' => \App\Models\User::role('warehouse_keeper')->count(),
]);"
```

قبل الترحيل يجب أن تكون جداول النطاق الأربعة غير موجودة.

## تشغيل الترحيل

```powershell
php artisan optimize:clear
php artisan migrate
php artisan permission:cache-reset
php artisan optimize:clear
```

لا حاجة إلى Seeder جديد.

## التحقق بعد Migration

```powershell
php artisan tinker --execute="dump([
    'area_scopes' => \Illuminate\Support\Facades\DB::table('user_area_scopes')->count(),
    'route_scopes' => \Illuminate\Support\Facades\DB::table('user_route_scopes')->count(),
    'vehicle_scopes' => \Illuminate\Support\Facades\DB::table('user_vehicle_scopes')->count(),
    'warehouse_scopes' => \Illuminate\Support\Facades\DB::table('user_warehouse_scopes')->count(),
    'users' => \App\Models\User::with([
        'roles:id,name',
        'accessAreas:id',
        'accessRoutes:id',
        'accessVehicles:id',
        'accessWarehouses:id',
    ])->get()->map(fn ($user) => [
        'email' => $user->email,
        'role' => $user->primaryRoleName(),
        'areas' => $user->accessAreas->count(),
        'routes' => $user->accessRoutes->count(),
        'vehicles' => $user->accessVehicles->count(),
        'warehouses' => $user->accessWarehouses->count(),
    ])->all(),
]);"
```

## الاختبارات

ابدأ باختبار المرحلة:

```powershell
php artisan test --filter=RoleDataScopeTest
```

ثم اختبارات RBAC:

```powershell
php artisan test --filter=RbacFoundationTest
```

ثم جميع الاختبارات:

```powershell
php artisan test
```

## الفحص اليدوي

### Supervisor

1. افتح مستخدم المشرف.
2. اختر منطقة واحدة ومستودعًا أو أكثر.
3. سجّل الدخول بالمشرف.
4. تحقق أن العملاء والخطوط والسيارات والعمليات والتقارير محصورة بالنطاق.
5. افتح رابط طباعة لسجل خارج النطاق؛ المتوقع `404` أو منع الوصول.

### Warehouse Keeper

1. عيّن مستودعين يحتاجهما أمر تحميل: المصدر والوجهة.
2. تحقق أن الأرصدة والحركات والتحميلات محصورة بهما.
3. أمر تحميل يلامس مستودعًا واحدًا يظهر للقراءة.
4. لا يمكن اعتماده إن كان الطرف الآخر خارج النطاق.

### Driver وSales Representative

1. اربط حساب المستخدم بموظف من النوع المطابق.
2. اربط الموظف بخط توزيع.
3. نطاقه يُشتق تلقائيًا دون تعيين يدوي.

## فحص ما قبل Git

```powershell
git diff --check
composer validate --no-check-publish
php artisan migrate:status
git status --short
```

ثم:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\data-scopes-git-add.ps1
git diff --cached --check
git diff --cached --stat
```

السكربت لا يستخدم `git add .`.

## التراجع أثناء التطوير

قبل وجود بيانات مهمة في جداول النطاق:

```powershell
php artisan migrate:rollback --step=1
```

ثم استرجع ملفات المشروع من Git.

عند وجود بيانات مهمة، استخدم نسخة قاعدة البيانات الاحتياطية بدل الاعتماد على rollback وحده.
