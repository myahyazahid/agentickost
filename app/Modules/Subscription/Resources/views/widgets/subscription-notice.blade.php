<x-filament-widgets::widget>
    <x-filament::section
        :heading="$heading"
        :description="$description"
        :icon="$urgent ? \Filament\Support\Icons\Heroicon::OutlinedExclamationTriangle : \Filament\Support\Icons\Heroicon::OutlinedCreditCard"
        :icon-color="$urgent ? 'danger' : 'gray'"
    >
        <x-slot name="afterHeader">
            <x-filament::button tag="a" :href="$url" size="sm" :color="$urgent ? 'primary' : 'gray'">
                Buka Langganan
            </x-filament::button>
        </x-slot>
    </x-filament::section>
</x-filament-widgets::widget>
