<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">أحدث العمليات</x-slot>
        <x-slot name="description">
            آخر المستندات المعتمدة ضمن صلاحيات حسابك.
        </x-slot>

        <div
            class="fi-scrollable"
            @style([
                'height: 32rem',
                'overflow-y: auto',
                'overflow-x: hidden',
            ])
        >
            @if ($activities === [])
                <x-filament::empty-state
                    heading="لا توجد حركات معتمدة"
                    description="لا توجد حركات معتمدة لعرضها حاليًا."
                    icon="heroicon-o-document-check"
                    compact
                />
            @else
                <div class="fi-sc-form fi-dense">
                    @foreach ($activities as $activity)
                        <x-filament::callout
                            :color="$activity['color']"
                            :icon="$activity['icon']"
                            :heading="$activity['title']"
                            :description="$activity['number'].' — '.$activity['description']"
                        >
                            <x-slot name="controls">
                                <div class="fi-ac">
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
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
