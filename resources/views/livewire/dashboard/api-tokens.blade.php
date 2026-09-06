<div class="space-y-6">
    @if ($plainTextToken !== null)
        <div class="rounded-xl border border-amber-300 bg-amber-50 p-4">
            <h3 class="text-sm font-semibold text-amber-900">
                {{ __('funnel.api_token.plain_text_heading') }}
            </h3>
            <p class="mt-1 text-sm text-amber-800">
                {{ __('funnel.api_token.plain_text_hint') }}
            </p>
            <code class="mt-3 block overflow-x-auto rounded-lg bg-white p-3 font-mono text-sm text-gray-900"
                  data-testid="plain-text-token">{{ $plainTextToken }}</code>
            <button type="button" wire:click="dismissPlainTextToken"
                    class="btn btn-sm mt-3">
                {{ __('funnel.api_token.plain_text_dismiss') }}
            </button>
        </div>
    @endif

    <form wire:submit="createToken" class="rounded-xl border border-gray-200 bg-white p-4 space-y-4">
        <div>
            <h3 class="text-base font-semibold text-gray-900">{{ __('funnel.api_token.create') }}</h3>
            <p class="text-sm text-gray-600">{{ __('funnel.api_token.description') }}</p>
        </div>

        <div>
            <label for="api-token-name" class="block text-sm font-medium text-gray-900">
                {{ __('funnel.api_token.name') }}
            </label>
            <input id="api-token-name" type="text" wire:model="name"
                   class="input input-bordered mt-1 w-full max-w-md"
                   placeholder="{{ __('funnel.api_token.name_placeholder') }}" />
            <p class="mt-1 text-xs text-gray-500">{{ __('funnel.api_token.name_helper') }}</p>
            @error('name')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <fieldset>
            <legend class="block text-sm font-medium text-gray-900">
                {{ __('funnel.api_token.abilities') }}
            </legend>
            <p class="mt-1 text-xs text-gray-500">{{ __('funnel.api_token.abilities_helper') }}</p>
            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                @foreach ($abilityOptions as $value => $label)
                    <label class="flex items-center gap-2 text-sm text-gray-900">
                        <input type="checkbox" class="checkbox checkbox-sm"
                               value="{{ $value }}" wire:model="abilities" />
                        <span>{{ $label }}</span>
                        <code class="text-xs text-gray-500">{{ $value }}</code>
                    </label>
                @endforeach
            </div>
            @error('abilities')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </fieldset>

        <button type="submit" class="btn btn-primary btn-sm">
            {{ __('funnel.api_token.create') }}
        </button>
    </form>

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('funnel.api_token.name') }}</th>
                    <th>{{ __('funnel.api_token.abilities') }}</th>
                    <th>{{ __('funnel.api_token.last_used_at') }}</th>
                    <th>{{ __('funnel.api_token.created_at') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tokens as $token)
                    <tr wire:key="token-{{ $token->id }}">
                        <td class="font-medium">{{ $token->name }}</td>
                        <td>
                            <div class="flex flex-wrap gap-1">
                                @foreach ($token->abilities ?? [] as $ability)
                                    <span class="badge badge-ghost badge-sm">
                                        {{ \App\Constants\TenantApiAbility::tryFrom($ability)?->label() ?? $ability }}
                                    </span>
                                @endforeach
                            </div>
                        </td>
                        <td>
                            {{ $token->last_used_at?->diffForHumans() ?? __('funnel.api_token.never_used') }}
                        </td>
                        <td>{{ $token->created_at?->format(config('app.datetime_format', 'd.m.Y H:i')) }}</td>
                        <td class="text-right">
                            <button type="button" class="btn btn-sm btn-error btn-outline"
                                    wire:click="revoke({{ $token->id }})"
                                    wire:confirm="{{ __('funnel.api_token.revoke_confirm') }}">
                                {{ __('funnel.api_token.revoke') }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-sm text-gray-500">
                            {{ __('funnel.api_token.empty') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
