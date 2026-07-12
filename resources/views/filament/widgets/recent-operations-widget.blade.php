<x-filament-widgets::widget>
    @include('filament.widgets.partials.executive-dashboard-styles')
    <x-filament::section>
        <x-slot name="heading">أحدث الحركات المهمة</x-slot>
        <x-slot name="description">
            آخر المستندات المعتمدة ضمن صلاحيات حسابك.
        </x-slot>

        <div class="fr-exec-dashboard">
        @if ($activities === [])
            <div class="fr-dashboard-empty">لا توجد حركات معتمدة لعرضها حاليًا.</div>
        @else
            <div class="fr-dashboard-activity-list">
                @foreach ($activities as $activity)
                    <a href="{{ $activity['url'] }}" class="fr-dashboard-activity" target="_blank">
                        <span class="fr-dashboard-activity__icon fr-dashboard-activity__icon--{{ $activity['color'] }}">
                            <x-filament::icon :icon="$activity['icon']" />
                        </span>
                        <span class="fr-dashboard-activity__content">
                            <strong>{{ $activity['title'] }}</strong>
                            <small>{{ $activity['number'] }} — {{ $activity['description'] }}</small>
                        </span>
                        <span class="fr-dashboard-activity__meta">
                            <strong>{{ number_format($activity['amount'], 2) }} ل.س</strong>
                            <small>{{ $activity['date'] }}</small>
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
