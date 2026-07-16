# تركيب Mobile Operational Read API — Phase 1

## نقطة البداية

يجب أن يكون المشروع على commit:

`b5a8eca Add mobile API foundation`

وأن تكون الشجرة نظيفة قبل فك الحزمة.

## التركيب

فك ملف ZIP مباشرة داخل جذر المشروع، ثم احذف ملف ZIP من المجلد.

لا توجد Migration جديدة في هذه المرحلة.

## تحديث مصفوفة الصلاحيات

أُضيفت للسائق والمندوب صلاحيات القراءة اللازمة للتطبيق: المنتجات، الخطوط، السيارات، والمستودعات. طبّق المصفوفة عبر:

```powershell
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan permission:cache-reset
php artisan optimize:clear
```

## التحقق من المسارات

```powershell
php artisan route:list --path=api/v1/operational
```

يجب أن تظهر مسارات bootstrap والقوائم والتفاصيل للوحدات التشغيلية.

## الاختبارات

```powershell
php artisan test --filter=MobileOperationalReadApiTest
php artisan test
```

## الاختبار اليدوي

1. سجّل الدخول من `/api/v1/auth/login`.
2. استخدم Bearer Token مع `/api/v1/operational/bootstrap`.
3. اختبر حساب مندوب مرتبط بخط، وتأكد أن العملاء والفواتير من الخط نفسه فقط.
4. اختبر حساب سائق، وتأكد أنه يستطيع قراءة خطه وسيارته ومخزونها وأوامر تحميلها، ولا يستطيع فتح العملاء.
5. جرّب رقم سجل خارج النطاق، ويجب أن تكون النتيجة 404.
6. تحقق أن `average_unit_cost` وحقول تكلفة التحميل لا تظهر للأدوار التي لا تملك `reports.profit`.

## Git

استخدم السكربت:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\mobile-operational-read-api-git-add.ps1
```

السكربت لا يستخدم `git add .`.
