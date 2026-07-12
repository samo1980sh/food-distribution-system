<x-filament-widgets::widget>
    @include('filament.widgets.partials.executive-dashboard-styles')
    <x-filament::section>
        <x-slot name="heading">الترتيب التنفيذي لهذا الشهر</x-slot>
        <x-slot name="description">
            أفضل العملاء وخطوط التوزيع وفق صافي المبيعات وصافي المساهمة.
        </x-slot>

        <div class="fr-exec-dashboard fr-dashboard-rankings">
            <section class="fr-dashboard-ranking-panel">
                <div class="fr-dashboard-ranking-panel__header">
                    <div>
                        <strong>أفضل العملاء</strong>
                        <span>حسب صافي المبيعات</span>
                    </div>
                    <a href="{{ route('filament.admin.resources.top-customer-reports.index') }}" wire:navigate>
                        عرض التقرير
                    </a>
                </div>

                @if ($top_customers === [])
                    <div class="fr-dashboard-empty">لا توجد مبيعات مؤكدة خلال الشهر الحالي.</div>
                @else
                    <div class="fr-dashboard-ranking-list">
                        @foreach ($top_customers as $customer)
                            <a href="{{ $customer['url'] }}" class="fr-dashboard-ranking-row" target="_blank">
                                <span class="fr-dashboard-rank">{{ $customer['rank'] }}</span>
                                <span class="fr-dashboard-ranking-main">
                                    <strong>{{ $customer['name'] }}</strong>
                                    <small>{{ $customer['code'] }} — {{ $customer['route'] }}</small>
                                </span>
                                <span class="fr-dashboard-ranking-metrics">
                                    <strong>{{ number_format($customer['net_sales'], 2) }} ل.س</strong>
                                    <small>
                                        {{ number_format($customer['invoice_count']) }} فاتورة
                                        · ربح {{ number_format($customer['profit'], 2) }}
                                    </small>
                                </span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="fr-dashboard-ranking-panel">
                <div class="fr-dashboard-ranking-panel__header">
                    <div>
                        <strong>أفضل خطوط التوزيع</strong>
                        <span>حسب صافي المساهمة</span>
                    </div>
                    <a href="{{ route('filament.admin.resources.route-performance-reports.index') }}" wire:navigate>
                        عرض التقرير
                    </a>
                </div>

                @if ($top_routes === [])
                    <div class="fr-dashboard-empty">لا توجد حركة مرتبطة بخطوط نشطة خلال الشهر الحالي.</div>
                @else
                    <div class="fr-dashboard-ranking-list">
                        @foreach ($top_routes as $route)
                            <a href="{{ $route['url'] }}" class="fr-dashboard-ranking-row" target="_blank">
                                <span class="fr-dashboard-rank">{{ $route['rank'] }}</span>
                                <span class="fr-dashboard-ranking-main">
                                    <strong>{{ $route['name'] }}</strong>
                                    <small>{{ $route['code'] }} — {{ $route['vehicle'] }}</small>
                                </span>
                                <span class="fr-dashboard-ranking-metrics">
                                    <strong>{{ number_format($route['net_contribution'], 2) }} ل.س</strong>
                                    <small>
                                        مبيعات {{ number_format($route['net_sales'], 2) }}
                                        · تحصيل {{ number_format($route['collections'], 2) }}
                                    </small>
                                </span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
