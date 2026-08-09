<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            التنبيهات التشغيلية
        </x-slot>

        <x-slot name="description">
            الحالات التي تحتاج متابعة حسب صلاحيات حسابك.
        </x-slot>

        <div class="grid gap-4">
            @if ($alerts === [])
                <x-filament::empty-state
                    heading="لا توجد تنبيهات عاجلة"
                    description="المؤشرات المتاحة ضمن صلاحياتك مستقرة حاليًا."
                    icon="heroicon-o-check-circle"
                    icon-color="success"
                    compact
                />
            @else
                <div class="grid gap-3">
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

            @if ($quickLinks !== [])
                <x-filament::section heading="وصول سريع" secondary compact>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($quickLinks as $link)
                            <x-filament::button
                                tag="a"
                                :href="$link['url']"
                                :icon="$link['icon']"
                                color="gray"
                                size="sm"
                                outlined
                                wire:navigate
                            >
                                {{ $link['label'] }}
                            </x-filament::button>
                        @endforeach
                    </div>
                </x-filament::section>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
