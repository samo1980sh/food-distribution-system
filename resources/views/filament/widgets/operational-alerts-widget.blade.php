<x-filament-widgets::widget>
    @include('filament.widgets.partials.executive-dashboard-styles')
    <x-filament::section>
        <x-slot name="heading">
            التنبيهات التشغيلية
        </x-slot>

        <x-slot name="description">
            الحالات التي تحتاج متابعة حسب صلاحيات حسابك.
        </x-slot>

        <div class="fr-exec-dashboard fr-operational-alerts">
            @if ($alerts === [])
                <div class="fr-operational-alerts__healthy">
                    <x-filament::icon
                        icon="heroicon-o-check-circle"
                        class="fr-operational-alerts__healthy-icon"
                    />

                    <div>
                        <strong>لا توجد تنبيهات عاجلة</strong>
                        <span>
                            المؤشرات المتاحة ضمن صلاحياتك مستقرة حاليًا.
                        </span>
                    </div>
                </div>
            @else
                <div class="fr-operational-alerts__list">
                    @foreach ($alerts as $alert)
                        <a
                            href="{{ $alert['url'] }}"
                            class="fr-operational-alert fr-operational-alert--{{ $alert['level'] }}"
                            wire:navigate
                        >
                            <span class="fr-operational-alert__icon">
                                <x-filament::icon
                                    :icon="$alert['icon']"
                                />
                            </span>

                            <span class="fr-operational-alert__content">
                                <strong>{{ $alert['title'] }}</strong>
                                <small>{{ $alert['description'] }}</small>
                            </span>

                            <span class="fr-operational-alert__value">
                                {{ $alert['value'] }}
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($quickLinks !== [])
                <div class="fr-dashboard-quick-links">
                    <span class="fr-dashboard-quick-links__title">
                        وصول سريع
                    </span>

                    <div class="fr-dashboard-quick-links__items">
                        @foreach ($quickLinks as $link)
                            <a
                                href="{{ $link['url'] }}"
                                class="fr-dashboard-quick-link"
                                wire:navigate
                            >
                                <x-filament::icon
                                    :icon="$link['icon']"
                                />

                                <span>{{ $link['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
