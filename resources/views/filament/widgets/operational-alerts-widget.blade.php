<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            تنبيهات تحتاج المتابعة
        </x-slot>

        <x-slot name="description">
            الحالات التي تحتاج متابعة حسب صلاحيات حسابك.
        </x-slot>

        <div
            class="fi-scrollable"
            @style([
                'height: 32rem',
                'overflow-y: auto',
                'overflow-x: hidden',
            ])
        >
            @if ($alerts === [])
                <x-filament::empty-state
                    heading="لا توجد تنبيهات عاجلة"
                    description="المؤشرات المتاحة ضمن صلاحياتك مستقرة حاليًا."
                    icon="heroicon-o-check-circle"
                    icon-color="success"
                    compact
                />
            @else
                <div class="fi-sc-form fi-dense">
                    @foreach ($alerts as $alert)
                        <x-filament::callout
                            :color="$alert['level']"
                            :icon="$alert['icon']"
                            :heading="$alert['title']"
                            :description="$alert['description']"
                        >
                            <x-slot name="controls">
                                <x-filament::badge :color="$alert['level']">
                                    {{ $alert['value'] }}
                                </x-filament::badge>
                            </x-slot>

                            <x-slot name="footer">
                                <x-filament::link
                                    :href="$alert['url']"
                                    :color="$alert['level']"
                                    icon="heroicon-m-arrow-left"
                                    wire:navigate
                                >
                                    عرض التفاصيل
                                </x-filament::link>
                            </x-slot>
                        </x-filament::callout>
                    @endforeach
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
