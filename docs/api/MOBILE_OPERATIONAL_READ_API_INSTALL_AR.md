# تركيب Mobile Operational Read API

## المتطلبات

- Mobile API وSanctum يعملان.
- المستخدم فعال ويحمل `sales_representative` و`api.access`.
- يوجد Employee فعال مرتبط بالحساب ومعين على DistributionRoute فعالة.
- الخط مرتبط بسيارة فعالة لها مستودع سيارة فعال.

## الفحص

```powershell
php artisan route:list --path=api/v1/operational
php artisan test --filter=MobileOperationalReadApiTest
php artisan test --filter=FieldTodayReadApiTest
php artisan test --filter=RoleDataScopeTest
```

بعد تسجيل الدخول استخدم Bearer Token مع:

```text
GET /api/v1/operational/bootstrap
GET /api/v1/operational/today
GET /api/v1/operational/routes
GET /api/v1/operational/stock-balances
```

## فحوص النطاق

- حساب المندوب لا يرى خط مندوب آخر.
- لا تظهر سيارة أو مستودع أو عميل خارج سياق الخط.
- السجل خارج النطاق يعاد 404 بدل كشف وجوده.
- بيانات التكلفة لا تظهر دون صلاحيتها.
- bootstrap لا يعرض إلا مساحة العمل الموحدة للمندوب.

## لا توجد كتابة ضمن القراءة

طلبات GET لا تنشئ `SalesJourney` أو زيارات. فتح الجولة والعمليات التشغيلية تستخدم endpoints الكتابة الموثقة بصورة مستقلة.
