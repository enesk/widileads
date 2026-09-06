<div class="grid gap-4 lg:grid-cols-12">

    {{-- Links: Eingabefelder --}}
    <section class="lg:col-span-5 rounded-xl border border-gray-200 bg-white p-4">
        <form wire:submit="save" class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
                @foreach ([
                    'primary_color' => __('builder.theme.primary_color'),
                    'secondary_color' => __('builder.theme.secondary_color'),
                    'background_color' => __('builder.theme.background_color'),
                    'text_color' => __('builder.theme.text_color'),
                ] as $field => $label)
                    <div>
                        <label for="t-{{ $field }}" class="block text-xs font-medium text-gray-700">{{ $label }}</label>
                        <div class="mt-1 flex items-center gap-2">
                            <input type="color" class="h-8 w-10 cursor-pointer rounded border border-gray-300"
                                   wire:model.live.debounce.400ms="{{ $field }}" />
                            <input id="t-{{ $field }}" type="text" class="input input-bordered input-sm w-full font-mono"
                                   wire:model.live.debounce.600ms="{{ $field }}" />
                        </div>
                        @error($field) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="t-font" class="block text-xs font-medium text-gray-700">{{ __('builder.theme.font_label') }}</label>
                    <select id="t-font" class="select select-bordered select-sm mt-1 w-full" wire:model.live="font">
                        @foreach ($fonts as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('font') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="t-progress" class="block text-xs font-medium text-gray-700">{{ __('builder.theme.progress_label') }}</label>
                    <select id="t-progress" class="select select-bordered select-sm mt-1 w-full" wire:model.live="progress_style">
                        @foreach ($progressStyles as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('progress_style') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="t-radius" class="block text-xs font-medium text-gray-700">
                    {{ __('builder.theme.border_radius') }}: {{ $border_radius }} px
                </label>
                <input id="t-radius" type="range" min="0" max="64" class="range range-sm mt-1 w-full"
                       wire:model.live="border_radius" />
                @error('border_radius') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="t-logo" class="block text-xs font-medium text-gray-700">{{ __('builder.theme.logo_path') }}</label>
                <input id="t-logo" type="text" class="input input-bordered input-sm mt-1 w-full"
                       wire:model.live.debounce.600ms="logo_path"
                       placeholder="{{ __('builder.theme.logo_placeholder') }}" />
                @error('logo_path') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <fieldset class="grid grid-cols-3 gap-3">
                <legend class="mb-1 block text-xs font-medium text-gray-700">{{ __('builder.theme.buttons') }}</legend>
                @foreach ([
                    'button_back_label' => __('builder.theme.default_back'),
                    'button_next_label' => __('builder.theme.default_next'),
                    'button_submit_label' => __('builder.theme.default_submit'),
                ] as $field => $placeholder)
                    <div>
                        <input type="text" class="input input-bordered input-sm w-full"
                               wire:model.live.debounce.600ms="{{ $field }}" placeholder="{{ $placeholder }}" />
                        @error($field) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endforeach
                <p class="col-span-3 text-xs text-gray-500">{{ __('builder.theme.buttons_hint') }}</p>
            </fieldset>

            <div class="flex items-center gap-3">
                <button type="submit" class="btn btn-primary btn-sm">{{ __('builder.theme.save') }}</button>
                @if ($saved)
                    <span class="text-sm text-green-700" wire:key="saved-hint">{{ __('builder.theme.saved') }}</span>
                @endif
            </div>

            <p class="text-xs text-gray-500">{{ __('builder.theme.publish_hint') }}</p>
        </form>
    </section>

    {{-- Rechts: Live-Vorschau --}}
    <section class="lg:col-span-7 rounded-xl border border-gray-200 bg-white p-4">
        <h2 class="text-sm font-semibold text-gray-900">{{ __('builder.theme.preview') }}</h2>
        <p class="mt-1 text-xs text-gray-500">{{ __('builder.theme.preview_hint') }}</p>
        <iframe src="{{ $previewUrl }}"
                title="{{ __('builder.theme.preview') }}"
                class="mt-3 h-[520px] w-full rounded-lg border border-gray-200"
                sandbox=""></iframe>
    </section>
</div>
