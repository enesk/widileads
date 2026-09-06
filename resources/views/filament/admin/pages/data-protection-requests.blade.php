<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">{{ __('funnel.gdpr.section_heading') }}</x-slot>

        <div class="prose dark:prose-invert max-w-none text-sm">
            <p>{{ __('funnel.gdpr.section_body') }}</p>
            <p>{{ __('funnel.gdpr.section_hint') }}</p>
        </div>
    </x-filament::section>
</x-filament-panels::page>
