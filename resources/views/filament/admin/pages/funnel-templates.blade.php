<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">{{ __('templates.section_heading') }}</x-slot>

        <div class="prose dark:prose-invert max-w-none text-sm">
            <p>{{ __('templates.section_body') }}</p>
            <p>{{ __('templates.section_hint') }}</p>

            @if (count($this->templateOptions()) === 0)
                <p>{{ __('templates.create.no_templates') }}</p>
            @else
                <ul>
                    @foreach ($this->templateOptions() as $key => $label)
                        <li>{{ $label }} <code>{{ $key }}</code></li>
                    @endforeach
                </ul>
            @endif
        </div>
    </x-filament::section>
</x-filament-panels::page>
