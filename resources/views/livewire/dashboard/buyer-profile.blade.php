{{--
    FB-051: Kaufkriterien pflegen. Tailwind und daisyUI, keine
    Filament-Komponenten.
--}}
<div class="space-y-6">
    <p class="text-sm opacity-70">{{ __('marketplace.profile.description') }}</p>

    @if ($saved)
        <div class="alert alert-success">
            <span>{{ __('marketplace.profile.saved') }}</span>
        </div>
    @endif

    <form wire:submit="save" class="space-y-6">

        {{-- Funnels --}}
        <div class="card bg-base-100 shadow">
            <div class="card-body">
                <h2 class="card-title text-base">{{ __('marketplace.profile.funnels') }}</h2>
                <p class="text-sm opacity-70">{{ __('marketplace.profile.funnels_helper') }}</p>

                @if (count($availableFunnels) === 0)
                    <p class="text-sm opacity-70">{{ __('marketplace.profile.no_funnels') }}</p>
                @else
                    <div class="mt-2 grid gap-2 md:grid-cols-2">
                        @foreach ($availableFunnels as $funnelId => $funnelName)
                            <label class="label cursor-pointer justify-start gap-3">
                                <input type="checkbox" class="checkbox"
                                       wire:model="funnelIds" value="{{ $funnelId }}">
                                <span class="label-text">{{ $funnelName }}</span>
                            </label>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Region --}}
        <div class="card bg-base-100 shadow">
            <div class="card-body">
                <h2 class="card-title text-base">{{ __('marketplace.profile.postal_prefixes') }}</h2>
                <p class="text-sm opacity-70">{{ __('marketplace.profile.postal_prefixes_helper') }}</p>

                <textarea wire:model="postalPrefixes" rows="2"
                          class="textarea textarea-bordered w-full mt-2"
                          placeholder="76, 77, 68"></textarea>
                @error('postalPrefixes')
                    <span class="text-error text-sm mt-1">{{ $message }}</span>
                @enderror
            </div>
        </div>

        {{-- Antwortfilter --}}
        <div class="card bg-base-100 shadow">
            <div class="card-body">
                <h2 class="card-title text-base">{{ __('marketplace.profile.answer_filters') }}</h2>
                <p class="text-sm opacity-70">{{ __('marketplace.profile.answer_filters_helper') }}</p>

                <div class="mt-2 space-y-2">
                    @forelse ($answerFilters as $index => $filter)
                        <div class="flex flex-col gap-2 md:flex-row md:items-center">
                            <input type="text" class="input input-bordered md:w-1/3"
                                   wire:model="answerFilters.{{ $index }}.field_key"
                                   placeholder="{{ __('marketplace.profile.field_key_placeholder') }}">
                            <input type="text" class="input input-bordered md:flex-1"
                                   wire:model="answerFilters.{{ $index }}.values"
                                   placeholder="{{ __('marketplace.profile.values_placeholder') }}">
                            <button type="button" class="btn btn-ghost btn-sm"
                                    wire:click="removeAnswerFilter({{ $index }})">
                                {{ __('marketplace.profile.remove_filter') }}
                            </button>
                        </div>
                    @empty
                        <p class="text-sm opacity-70">{{ __('marketplace.profile.no_answer_filters') }}</p>
                    @endforelse
                </div>

                <button type="button" class="btn btn-outline btn-sm mt-3" wire:click="addAnswerFilter">
                    {{ __('marketplace.profile.add_filter') }}
                </button>
            </div>
        </div>

        {{-- Punktzahl, Autokauf, Benachrichtigung --}}
        <div class="card bg-base-100 shadow">
            <div class="card-body space-y-4">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="form-control">
                        <label class="label" for="min-score">
                            <span class="label-text">{{ __('marketplace.profile.min_score') }}</span>
                        </label>
                        <input id="min-score" type="number" wire:model="minScore"
                               class="input input-bordered w-full">
                        <span class="text-sm opacity-70 mt-1">{{ __('marketplace.profile.min_score_helper') }}</span>
                        @error('minScore')
                            <span class="text-error text-sm mt-1">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-control">
                        <label class="label" for="daily-limit">
                            <span class="label-text">{{ __('marketplace.profile.daily_limit') }}</span>
                        </label>
                        <input id="daily-limit" type="number" min="0" wire:model="dailyLimit"
                               class="input input-bordered w-full">
                        <span class="text-sm opacity-70 mt-1">{{ __('marketplace.profile.daily_limit_helper') }}</span>
                        @error('dailyLimit')
                            <span class="text-error text-sm mt-1">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="form-control">
                    <label class="label cursor-pointer justify-start gap-3">
                        <input type="checkbox" wire:model="autoBuy" class="checkbox">
                        <span class="label-text">{{ __('marketplace.profile.auto_buy') }}</span>
                    </label>
                    <span class="text-sm opacity-70">{{ __('marketplace.profile.auto_buy_helper') }}</span>
                </div>

                <div class="form-control">
                    <label class="label" for="notify-email">
                        <span class="label-text">{{ __('marketplace.profile.notify_email') }}</span>
                    </label>
                    <input id="notify-email" type="email" wire:model="notifyEmail"
                           class="input input-bordered w-full">
                    <span class="text-sm opacity-70 mt-1">{{ __('marketplace.profile.notify_email_helper') }}</span>
                    @error('notifyEmail')
                        <span class="text-error text-sm mt-1">{{ $message }}</span>
                    @enderror
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
            {{ __('marketplace.profile.submit') }}
        </button>
    </form>
</div>
