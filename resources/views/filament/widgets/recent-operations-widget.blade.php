<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">أحدث الحركات المهمة</x-slot>
        <x-slot name="description">
            آخر المستندات المعتمدة ضمن صلاحيات حسابك.
        </x-slot>

        @if ($activities === [])
            <x-filament::empty-state
                heading="لا توجد حركات معتمدة"
                description="لا توجد حركات معتمدة لعرضها حاليًا."
                icon="heroicon-o-document-check"
                compact
            />
        @else
            <div class="grid gap-3">
                @foreach ($activities as $activity)
                    <x-filament::callout
                        :color="$activity['color']"
                        :icon="$activity['icon']"
                        :heading="$activity['title']"
                        :description="$activity['number'].' — '.$activity['description']"
                    >
                        <x-slot name="controls">
                            <div class="flex flex-wrap gap-2">
                                <x-filament::badge :color="$activity['color']">
                                    {{ number_format($activity['amount'], 2) }} ل.س
                                </x-filament::badge>
                                <x-filament::badge color="gray">
                                    {{ $activity['date'] }}
                                </x-filament::badge>
                            </div>
                        </x-slot>

                        <x-slot name="footer">
                            <x-filament::link
                                :href="$activity['url']"
                                :color="$activity['color']"
                                icon="heroicon-m-arrow-top-right-on-square"
                                target="_blank"
                            >
                                عرض المستند
                            </x-filament::link>
                        </x-slot>
                    </x-filament::callout>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
