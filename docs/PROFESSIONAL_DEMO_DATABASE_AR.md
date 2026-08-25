# قاعدة البيانات التجريبية الاحترافية

## الغرض

تجهز Professional Demo بيانات مترابطة لتجربة لوحة الإدارة والتقارير وMobile API ودورة مندوب المبيعات الموحدة. الأمر مخصص لبيئات `local` و`testing` فقط.

## تحذير

```text
php artisan demo:reset --apply
```

يعيد بناء قاعدة البيانات المستهدفة بالكامل باستخدام migrations والـseeders. لا تستخدمه على قاعدة تحتوي بيانات مهمة. ابدأ دائمًا بالفحص غير المعدل:

```powershell
php artisan demo:reset --check
```

يعرض الفحص البيئة والاتصال وقاعدة البيانات وأعداد السجلات التي ستحذف وخطة البيانات التي ستنشأ، ولا يكتب أي بيانات.

بعد التأكد من أن القاعدة مخصصة للتجربة فقط:

```powershell
php artisan demo:reset --apply
```

الخيار `--force` يتجاوز سؤال التأكيد في البيئات غير الإنتاجية، ولا يجعله آمنًا لقاعدة مهمة.

## الحسابات

كلمة المرور المشتركة: `Demo@2026`.

| الاستخدام | البريد | الدور |
|---|---|---|
| إدارة النظام | `admin@demo.local` | Super Admin |
| الإدارة والتقارير | `manager@demo.local` | Manager |
| إشراف دمشق | `supervisor@demo.local` | Supervisor |
| عمليات المستودع | `warehouse@demo.local` | Warehouse Keeper |
| المحاسبة | `accountant@demo.local` | Accountant |
| مندوب دمشق المركزي | `sales@demo.local` | Sales Representative |
| مندوب دمشق الجنوبي | `field.team@demo.local` | Sales Representative |
| مندوب ريف دمشق | `sales.rif@demo.local` | Sales Representative |

لا تنشئ البيانات التجريبية أي دور ميداني آخر.

## محتوى البيانات

- مناطق وخطوط توزيع فعالة وغير فعالة لتجربة الفلاتر والنطاقات.
- سيارات ومستودعات رئيسية وفرعية ومستودعات سيارات.
- عملاء نقديين وآجلين وجزئيين مع حالات مسددة ومتأخرة وجديدة.
- منتجات وتصنيفات ووحدات وأسعار بيع وشراء.
- أرصدة افتتاحية وتشغيلات وتواريخ صلاحية وحركات مخزون وتكلفة متوسطة.
- أوامر تحميل معتمدة ومسودة، وحمولات اليوم المعلقة لاختبار handover.
- فواتير confirmed/draft/cancelled بأنواع Cash/Credit/Partial.
- تحصيلات ومرتجعات ومصاريف سيارات بحالات متعددة.
- إغلاقات يومية مؤكدة ومسودة مع تفاصيل المخزون والنقد.
- بيانات تغطي تقارير المبيعات والربح والتحصيل والعملاء والمخزون والصلاحية وأداء الخطوط.

## سيناريو Flutter الجاهز

ينشئ `ProfessionalFieldWorkspacesSeeder` لليوم:

- ثلاثة خطوط فعالة مجدولة لحسابات المندوبين.
- `SalesJourney` جاهزة لكل حساب.
- زيارات معلقة لعملاء كل خط.
- أوامر تحميل معتمدة تنتظر الاستلام الصريح.
- مخزون سيارة وسياق route/vehicle/warehouse صالح.

السيناريو العملي:

1. تسجيل الدخول بحساب مندوب.
2. مراجعة مساحة عمل اليوم.
3. استلام أمر التحميل ومعالجة الفرق إن وجد.
4. بدء الجولة والزيارة.
5. إنشاء فاتورة أو تحصيل أو مرتجع وتسجيل مصروف عند الحاجة.
6. إكمال الزيارات وإنهاء الجولة.
7. إرسال جرد السيارة والنقد.
8. مراجعة الإغلاق من لوحة الإدارة.

## بنية الـseeders

- `ProfessionalCatalogSeeder`: التصنيفات والوحدات والمنتجات والعملاء الأساسيون.
- `ProfessionalUsersAndDistributionSeeder`: المستخدمون والموظفون والمناطق والخطوط والسيارات والمستودعات.
- `ProfessionalOperationsSeeder`: المخزون والتحميلات والفواتير والتحصيلات والمرتجعات والمصاريف والإغلاقات.
- `ProfessionalFieldWorkspacesSeeder`: جولات وزيارات اليوم للمندوبين.
- `ProfessionalDemoDatabaseSeeder`: نقطة التجميع لقاعدة demo كاملة.

## التحقق

```powershell
php artisan test --filter=ProfessionalDemoDatabaseTest
php artisan test --filter=SalesFieldOperationsApiTest
php artisan test --filter=UnifiedRepresentativeJourneyApiTest
php artisan test --filter=VehicleLoadHandoverApiTest
```

يجب أن تثبت الاختبارات وجود حسابات المندوبين والجولات والزيارات والعمليات الحالية، واستقرار إعادة تشغيل الـseeders، وعدم إنشاء أي مجال ميداني موازٍ.
