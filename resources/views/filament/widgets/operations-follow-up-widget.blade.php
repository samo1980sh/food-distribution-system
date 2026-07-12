<x-filament-widgets::widget>
    @include('filament.widgets.partials.executive-dashboard-styles')
    <x-filament::section>
        <x-slot name="heading">متابعة السيارات والمستودعات</x-slot>
        <x-slot name="description">
            الوثائق والحالات التشغيلية التي تحتاج إجراءً قريبًا.
        </x-slot>

        <div class="fr-exec-dashboard">
        @if ($items === [])
            <div class="fr-dashboard-follow-up-ok">
                <x-filament::icon icon="heroicon-o-check-circle" />
                <div>
                    <strong>لا توجد حالات متابعة عاجلة</strong>
                    <span>السيارات والمستودعات المتاحة ضمن صلاحياتك مستقرة.</span>
                </div>
            </div>
        @else
            <div class="fr-dashboard-follow-up-list">
                @foreach ($items as $item)
                    <a href="{{ $item['url'] }}" class="fr-dashboard-follow-up fr-dashboard-follow-up--{{ $item['level'] }}" wire:navigate>
                        <span class="fr-dashboard-follow-up__icon">
                            <x-filament::icon :icon="$item['icon']" />
                        </span>
                        <span class="fr-dashboard-follow-up__content">
                            <strong>{{ $item['title'] }}</strong>
                            <small>{{ $item['description'] }}</small>
                        </span>
                        <span class="fr-dashboard-follow-up__value">{{ $item['value'] }}</span>
                    </a>
                @endforeach
            </div>
        @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
