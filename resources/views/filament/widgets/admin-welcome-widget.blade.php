<x-filament-widgets::widget>
    <x-filament::section
        heading="لوحة تشغيل توزيع المواد الغذائية والأسطول"
        description="تابع السيارات النشطة، فواتير البيع، تحصيلات العملاء، مصاريف السيارات، وإغلاقات الأيام من لوحة واحدة مرتبطة بالعمليات الفعلية للنظام."
        icon="heroicon-o-squares-2x2"
        icon-color="primary"
    >
        <div class="flex flex-wrap gap-2">
            <x-filament::badge color="gray">السيارة = مستودع متنقل</x-filament::badge>
            <x-filament::badge color="primary">مبيعات يومية</x-filament::badge>
            <x-filament::badge color="success">تحصيلات ومطابقة</x-filament::badge>
            <x-filament::badge color="warning">مصاريف سيارات</x-filament::badge>
            <x-filament::badge color="info">إغلاق يومي</x-filament::badge>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
