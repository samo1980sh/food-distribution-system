<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">الترتيب التنفيذي لهذا الشهر</x-slot>
        <x-slot name="description">
            أفضل العملاء وخطوط التوزيع وفق صافي المبيعات وصافي المساهمة.
        </x-slot>

        <div class="grid gap-4 xl:grid-cols-2">
            <x-filament::section
                heading="أفضل العملاء"
                description="حسب صافي المبيعات"
                icon="heroicon-o-user-group"
                secondary
            >
                <x-slot name="afterHeader">
                    <x-filament::link
                        :href="route('filament.admin.resources.top-customer-reports.index')"
                        icon="heroicon-m-arrow-left"
                        size="sm"
                        wire:navigate
                    >
                        عرض التقرير
                    </x-filament::link>
                </x-slot>

                @if ($top_customers === [])
                    <x-filament::empty-state
                        heading="لا توجد مبيعات مؤكدة"
                        description="لا توجد مبيعات مؤكدة خلال الشهر الحالي."
                        icon="heroicon-o-chart-bar-square"
                        compact
                    />
                @else
                    <div class="grid gap-3">
                        @foreach ($top_customers as $customer)
                            <x-filament::section
                                :heading="$customer['name']"
                                :description="$customer['code'].' — '.$customer['route']"
                                compact
                            >
                                <x-slot name="afterHeader">
                                    <x-filament::badge color="primary">
                                        {{ $customer['rank'] }}
                                    </x-filament::badge>
                                </x-slot>

                                <div class="flex flex-wrap items-center gap-2">
                                    <x-filament::badge color="success">
                                        صافي المبيعات: {{ number_format($customer['net_sales'], 2) }} ل.س
                                    </x-filament::badge>
                                    <x-filament::badge color="gray">
                                        {{ number_format($customer['invoice_count']) }} فاتورة
                                    </x-filament::badge>
                                    <x-filament::badge color="info">
                                        الربح: {{ number_format($customer['profit'], 2) }} ل.س
                                    </x-filament::badge>
                                </div>

                                <x-slot name="footer">
                                    <x-filament::link
                                        :href="$customer['url']"
                                        icon="heroicon-m-arrow-top-right-on-square"
                                        target="_blank"
                                    >
                                        عرض التفاصيل
                                    </x-filament::link>
                                </x-slot>
                            </x-filament::section>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>

            <x-filament::section
                heading="أفضل خطوط التوزيع"
                description="حسب صافي المساهمة"
                icon="heroicon-o-map"
                secondary
            >
                <x-slot name="afterHeader">
                    <x-filament::link
                        :href="route('filament.admin.resources.route-performance-reports.index')"
                        icon="heroicon-m-arrow-left"
                        size="sm"
                        wire:navigate
                    >
                        عرض التقرير
                    </x-filament::link>
                </x-slot>

                @if ($top_routes === [])
                    <x-filament::empty-state
                        heading="لا توجد حركة على الخطوط"
                        description="لا توجد حركة مرتبطة بخطوط نشطة خلال الشهر الحالي."
                        icon="heroicon-o-map"
                        compact
                    />
                @else
                    <div class="grid gap-3">
                        @foreach ($top_routes as $route)
                            <x-filament::section
                                :heading="$route['name']"
                                :description="$route['code'].' — '.$route['vehicle']"
                                compact
                            >
                                <x-slot name="afterHeader">
                                    <x-filament::badge color="primary">
                                        {{ $route['rank'] }}
                                    </x-filament::badge>
                                </x-slot>

                                <div class="flex flex-wrap items-center gap-2">
                                    <x-filament::badge color="success">
                                        صافي المساهمة: {{ number_format($route['net_contribution'], 2) }} ل.س
                                    </x-filament::badge>
                                    <x-filament::badge color="gray">
                                        المبيعات: {{ number_format($route['net_sales'], 2) }} ل.س
                                    </x-filament::badge>
                                    <x-filament::badge color="info">
                                        التحصيل: {{ number_format($route['collections'], 2) }} ل.س
                                    </x-filament::badge>
                                </div>

                                <x-slot name="footer">
                                    <x-filament::link
                                        :href="$route['url']"
                                        icon="heroicon-m-arrow-top-right-on-square"
                                        target="_blank"
                                    >
                                        عرض التفاصيل
                                    </x-filament::link>
                                </x-slot>
                            </x-filament::section>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
