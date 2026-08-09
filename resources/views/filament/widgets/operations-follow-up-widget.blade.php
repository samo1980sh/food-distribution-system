<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">متابعة السيارات والمستودعات</x-slot>
        <x-slot name="description">
            الوثائق والحالات التشغيلية التي تحتاج إجراءً قريبًا.
        </x-slot>

        @if ($items === [])
            <x-filament::empty-state
                heading="لا توجد حالات متابعة عاجلة"
                description="السيارات والمستودعات المتاحة ضمن صلاحياتك مستقرة."
                icon="heroicon-o-check-circle"
                icon-color="success"
                compact
            />
        @else
            <div class="grid gap-3">
                @foreach ($items as $item)
                    <x-filament::callout
                        :color="$item['level']"
                        :icon="$item['icon']"
                        :heading="$item['title']"
                        :description="$item['description']"
                    >
                        <x-slot name="controls">
                            <x-filament::badge :color="$item['level']">
                                {{ $item['value'] }}
                            </x-filament::badge>
                        </x-slot>

                        <x-slot name="footer">
                            <x-filament::link
                                :href="$item['url']"
                                :color="$item['level']"
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
    </x-filament::section>
</x-filament-widgets::widget>
