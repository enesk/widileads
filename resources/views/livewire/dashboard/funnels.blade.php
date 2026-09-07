<div class="space-y-4">

    @if ($previewLink !== null)
        <div class="rounded-xl border border-sky-300 bg-sky-50 p-4" role="status">
            <h3 class="text-sm font-semibold text-sky-900">{{ __('builder.funnels.preview_heading') }}</h3>
            <p class="mt-1 text-sm text-sky-800">{{ __('builder.funnels.preview_hint') }}</p>
            <code class="mt-2 block overflow-x-auto rounded bg-white p-2 text-xs">{{ $previewLink }}</code>
            <button type="button" class="btn btn-xs mt-2" wire:click="dismissPreviewLink">
                {{ __('builder.funnels.preview_dismiss') }}
            </button>
        </div>
    @endif

    <form wire:submit="create" class="flex flex-wrap items-end gap-2 rounded-xl border border-gray-200 bg-white p-4">
        <div class="flex-1 min-w-[16rem]">
            <label for="new-funnel" class="block text-xs font-medium text-gray-700">
                {{ __('builder.funnels.new_name') }}
            </label>
            <input id="new-funnel" type="text" class="input input-bordered input-sm mt-1 w-full"
                   wire:model="newName" placeholder="{{ __('builder.funnels.new_placeholder') }}"
                   @error('newName') aria-invalid="true" aria-describedby="new-funnel-error" @enderror />
            @error('newName')
                <p id="new-funnel-error" class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
            @enderror
        </div>
        <button type="submit" class="btn btn-primary btn-sm">{{ __('builder.funnels.create') }}</button>
    </form>

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
        <table class="table">
            <caption class="sr-only">{{ __('builder.funnels.table_caption') }}</caption>
            <thead>
                <tr>
                    <th scope="col">{{ __('builder.funnels.name') }}</th>
                    <th scope="col">{{ __('builder.funnels.status') }}</th>
                    <th scope="col">{{ __('builder.funnels.size') }}</th>
                    <th scope="col">{{ __('builder.funnels.edit') }}</th>
                    <th scope="col">{{ __('builder.funnels.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($funnels as $funnel)
                    <tr wire:key="funnel-{{ $funnel->id }}">
                        <td>
                            <span class="font-medium">{{ $funnel->name }}</span>
                            <code class="ml-1 text-xs text-gray-500">{{ $funnel->slug }}</code>
                        </td>
                        <td>
                            <span @class([
                                'badge badge-sm',
                                'badge-success' => $funnel->status === \App\Constants\FunnelStatus::PUBLISHED,
                                'badge-ghost' => $funnel->status === \App\Constants\FunnelStatus::DRAFT,
                                'badge-neutral' => $funnel->status === \App\Constants\FunnelStatus::ARCHIVED,
                            ])>{{ $funnel->status->label() }}</span>
                        </td>
                        <td class="text-sm text-gray-600">
                            {{ __('builder.funnels.counts', [
                                'steps' => $funnel->steps_count,
                                'questions' => $funnel->questions_count,
                            ]) }}
                        </td>
                        <td>
                            <div class="flex flex-wrap gap-1">
                                <a class="btn btn-xs"
                                   href="{{ \App\Filament\Dashboard\Pages\FunnelBuilder::getUrl(['funnel' => $funnel], panel: 'dashboard', tenant: $tenant) }}">
                                    {{ __('builder.funnels.open_builder') }}
                                </a>
                                <a class="btn btn-xs"
                                   href="{{ \App\Filament\Dashboard\Pages\FunnelRules::getUrl(['funnel' => $funnel], panel: 'dashboard', tenant: $tenant) }}">
                                    {{ __('builder.funnels.open_rules') }}
                                </a>
                                <a class="btn btn-xs"
                                   href="{{ \App\Filament\Dashboard\Pages\ThemeEditor::getUrl(['funnel' => $funnel], panel: 'dashboard', tenant: $tenant) }}">
                                    {{ __('builder.funnels.open_theme') }}
                                </a>
                                {{-- FB-055a: ohne diesen Weg waere die Verkaufsart nur ueber einen Seeder erreichbar. --}}
                                <a class="btn btn-xs"
                                   href="{{ \App\Filament\Dashboard\Pages\FunnelSaleSettings::getUrl(['funnel' => $funnel], panel: 'dashboard', tenant: $tenant) }}">
                                    {{ __('builder.funnels.open_sale') }}
                                </a>
                            </div>
                        </td>
                        <td>
                            <div class="flex flex-wrap gap-1">
                                <button type="button" class="btn btn-xs btn-primary"
                                        wire:click="publish({{ $funnel->id }})"
                                        aria-label="{{ __('builder.funnels.publish_named', ['name' => $funnel->name]) }}">
                                    {{ __('builder.funnels.publish') }}
                                </button>
                                <button type="button" class="btn btn-xs"
                                        wire:click="previewLink({{ $funnel->id }})"
                                        aria-label="{{ __('builder.funnels.preview_named', ['name' => $funnel->name]) }}">
                                    {{ __('builder.funnels.preview') }}
                                </button>
                                <button type="button" class="btn btn-xs"
                                        wire:click="duplicate({{ $funnel->id }})"
                                        aria-label="{{ __('builder.funnels.duplicate_named', ['name' => $funnel->name]) }}">
                                    {{ __('builder.funnels.duplicate') }}
                                </button>
                                @if ($funnel->status !== \App\Constants\FunnelStatus::ARCHIVED)
                                    <button type="button" class="btn btn-xs btn-outline"
                                            wire:click="archive({{ $funnel->id }})"
                                            wire:confirm="{{ __('builder.funnels.confirm_archive') }}"
                                            aria-label="{{ __('builder.funnels.archive_named', ['name' => $funnel->name]) }}">
                                        {{ __('builder.funnels.archive') }}
                                    </button>
                                @endif
                            </div>

                            @if (! empty($publishProblems[$funnel->id]))
                                <div class="mt-2 rounded-lg border border-amber-300 bg-amber-50 p-2" role="alert">
                                    <p class="text-xs font-medium text-amber-900">
                                        {{ __('builder.funnels.not_publishable') }}
                                    </p>
                                    <ul class="mt-1 list-inside list-disc text-xs text-amber-900">
                                        @foreach ($publishProblems[$funnel->id] as $reason)
                                            <li>{{ $reason }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-sm text-gray-500">
                            {{ __('builder.funnels.empty') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
