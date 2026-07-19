<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">زاوية التحليل</x-slot>
        <x-slot name="description">
            {{ $this->getAnalysisViewDescription() }}
        </x-slot>

        <x-filament::tabs label="زوايا تحليل أداء خطوط التوزيع">
            @foreach ($this->getAnalysisViews() as $viewKey => $view)
                <x-filament::tabs.item
                    :active="$this->analysisView === $viewKey"
                    :icon="$view['icon']"
                    wire:click="setAnalysisView('{{ $viewKey }}')"
                    wire:key="route-performance-analysis-{{ $viewKey }}"
                >
                    {{ $view['label'] }}
                </x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>
    </x-filament::section>

    {{ $this->table }}
</x-filament-panels::page>
