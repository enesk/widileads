{{-- FB-073: Exporte anfordern und herunterladen. Der Download-Link wird beim
     Rendern erzeugt und ist befristet. --}}
<div class="flex flex-col gap-6">
    <section class="rounded-box bg-base-100 p-4 shadow">
        <h2 class="text-base font-semibold">{{ __('exports.new') }}</h2>
        <p class="mt-1 text-sm text-base-content/70">{{ __('exports.hint') }}</p>

        <fieldset class="mt-4">
            <legend class="text-sm text-base-content/60">{{ __('exports.columns_legend') }}</legend>

            <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
                @foreach ($availableColumns as $column)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" value="{{ $column }}" wire:model="columns" class="checkbox checkbox-sm">
                        <span>{{ __('exports.columns.'.$column) }}</span>
                    </label>
                @endforeach
            </div>

            @error('columns')
                <p class="mt-2 text-sm text-error">{{ $message }}</p>
            @enderror
        </fieldset>

        <button type="button" wire:click="requestExport" class="btn btn-primary mt-4">
            {{ __('exports.request') }}
        </button>
    </section>

    <section class="rounded-box bg-base-100 shadow">
        <div class="overflow-x-auto">
            <table class="table">
                <caption class="sr-only">{{ __('exports.heading') }}</caption>
                <thead>
                    <tr>
                        <th scope="col">{{ __('exports.requested_at') }}</th>
                        <th scope="col">{{ __('exports.requested_by') }}</th>
                        <th scope="col">{{ __('exports.status_label') }}</th>
                        <th scope="col" class="text-right">{{ __('exports.rows') }}</th>
                        <th scope="col"><span class="sr-only">{{ __('exports.download') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($exports as $row)
                        <tr wire:key="export-{{ $row['export']->id }}">
                            <td class="whitespace-nowrap">{{ $row['export']->created_at?->format('d.m.Y H:i') }}</td>
                            <td>{{ $row['export']->requester?->name ?? '-' }}</td>
                            <td>
                                {{ $row['export']->status->label() }}
                                @if ($row['export']->failure_reason)
                                    <span class="block text-xs text-error">{{ $row['export']->failure_reason }}</span>
                                @endif
                            </td>
                            <td class="text-right">{{ $row['export']->row_count ?? '-' }}</td>
                            <td class="text-right">
                                @if ($row['download'])
                                    <a href="{{ $row['download'] }}" class="btn btn-sm">{{ __('exports.download') }}</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-base-content/60">
                                {{ __('exports.empty') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
