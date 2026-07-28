# Dynamic Phase 3B — Vehicle Loads Read Projection

يوسّع هذا الجزء عقد القراءة الحالي لأوامر التحميل دون تغيير مسؤوليات المخزون أو الاستلام.

## التغييرات

- إضافة `items_count` إلى قائمة وتفاصيل أمر التحميل.
- إضافة `different_items_count` للكميات المستلمة المختلفة فعليًا.
- إضافة حقول مسطحة آمنة لبند التحميل:
  - `product_id`
  - `product_sku`
  - `product_name`
  - `unit_label`
- الإبقاء على الموارد المتداخلة الحالية للتوافق الخلفي.

## غير المشمول

- لا توجد Migration.
- لا توجد حركة مخزون جديدة.
- لا يتغير عقد `acknowledge`.
- لا تتغير Offline Push أو Idempotency أو Sync Queue.
