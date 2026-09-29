{{--
    "Profil" im Portal.

    Vom Telefon aus gedacht: eine Spalte mit Karten, ab der grossen Breite die
    Karten links und die Uebersicht rechts. Jede Karte ist ein eigener Vorgang
    mit eigenem Knopf -- ein gemeinsames "Speichern" ueber Name, E-Mail und
    Passwort haette bei jedem Namenstausch nach dem Passwort verlangt.

    Erwartete Daten:
      $user, $initial, $emailChanged, $isVerified, $memberSince, $workspaceName
      $showPasswordForm, $twoFactorEnabled, $twoFactorActive, $twoFactorUrl
      $settingsUrl
--}}
<div>

    <div>
        <h1 class="text-2xl md:text-4xl font-bold tracking-tight text-zinc-900">{{ __('portal.profile.heading') }}</h1>
        <p class="text-zinc-500 mt-1 max-w-prose">{{ __('portal.profile.description') }}</p>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_20rem] items-start">

        <div class="min-w-0 space-y-4">

            {{-- Konto --}}
            <x-app.card class="p-5">
                <h2 class="font-semibold text-zinc-900">{{ __('portal.profile.account.heading') }}</h2>

                <form wire:submit="saveAccount" class="mt-4 space-y-4">
                    <div>
                        <label for="profile-name" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.profile.account.name') }}</label>
                        <input id="profile-name" type="text" class="input" autocomplete="name" wire:model="name">
                        @error('name')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="profile-email" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.profile.account.email') }}</label>
                        <input id="profile-email" type="email" class="input" autocomplete="email" wire:model.live.debounce.500ms="email">
                        @error('email')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror

                        @if (! $isVerified)
                            <p class="mt-2 text-sm text-amber-800 flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center gap-1.5">
                                    <x-app.icon name="alert" class="size-4 shrink-0" />
                                    {{ __('portal.profile.account.unverified') }}
                                </span>
                                <button type="button" wire:click="resendVerification" class="text-brand font-medium hover:underline">
                                    {{ __('portal.profile.account.resend') }}
                                </button>
                            </p>
                        @endif
                    </div>

                    {{-- Das Passwortfeld erscheint erst, wenn die Adresse
                         wirklich abweicht: Wer nur seinen Namen korrigiert,
                         soll es nicht vor sich haben. --}}
                    @if ($emailChanged)
                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                            <p class="text-sm text-amber-900">{{ __('portal.profile.account.email_warning') }}</p>

                            <label for="profile-email-password" class="block text-sm font-medium text-zinc-700 mt-3 mb-1">{{ __('portal.profile.account.current_password') }}</label>
                            <input id="profile-email-password" type="password" class="input" autocomplete="current-password" wire:model="emailPassword">
                            @error('emailPassword')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                    @endif

                    <div class="flex justify-end">
                        <button type="submit" class="btn-primary w-full sm:w-auto" wire:loading.attr="disabled">
                            {{ __('portal.profile.save') }}
                        </button>
                    </div>
                </form>
            </x-app.card>

            {{-- Passwort --}}
            @if ($showPasswordForm)
                <x-app.card class="p-5">
                    <h2 class="font-semibold text-zinc-900">{{ __('portal.profile.password.heading') }}</h2>
                    <p class="text-sm text-zinc-500 mt-1">{{ __('portal.profile.password.description') }}</p>

                    <form wire:submit="savePassword" class="mt-4 space-y-4">
                        <div>
                            <label for="profile-current" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.profile.account.current_password') }}</label>
                            <input id="profile-current" type="password" class="input" autocomplete="current-password" wire:model="currentPassword">
                            @error('currentPassword')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="profile-new" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.profile.password.new') }}</label>
                                <input id="profile-new" type="password" class="input" autocomplete="new-password" wire:model="password">
                                @error('password')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                                <p class="text-xs text-zinc-500 mt-1">{{ __('portal.profile.password.hint') }}</p>
                            </div>

                            <div>
                                <label for="profile-confirm" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.profile.password.confirm') }}</label>
                                <input id="profile-confirm" type="password" class="input" autocomplete="new-password" wire:model="passwordConfirmation">
                                @error('passwordConfirmation')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="btn-primary w-full sm:w-auto" wire:loading.attr="disabled">
                                {{ __('portal.profile.password.submit') }}
                            </button>
                        </div>
                    </form>
                </x-app.card>
            @endif

            {{-- Zwei-Faktor: hier steht der Stand, eingerichtet wird weiter im
                 Dashboard-Panel. --}}
            @if ($twoFactorEnabled)
                <x-app.card class="p-5 flex items-center gap-4">
                    <span class="size-11 rounded-xl bg-zinc-100 text-zinc-500 flex items-center justify-center shrink-0">
                        <x-app.icon name="lock" class="size-5" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-zinc-900">{{ __('portal.profile.two_factor.heading') }}</p>
                        <p class="text-sm text-zinc-500">
                            {{ $twoFactorActive ? __('portal.profile.two_factor.active') : __('portal.profile.two_factor.inactive') }}
                        </p>
                    </div>

                    <a href="{{ $twoFactorUrl }}" class="btn-secondary shrink-0">
                        {{ $twoFactorActive ? __('portal.profile.two_factor.manage') : __('portal.profile.two_factor.setup') }}
                    </a>
                </x-app.card>
            @endif
        </div>

        {{-- Die ruhige Spalte: wer man ist, seit wann, in welchem Workspace. --}}
        <x-app.card class="p-5">
            <div class="flex items-center gap-3">
                <span class="size-12 rounded-full bg-brand text-white text-lg font-semibold flex items-center justify-center shrink-0">{{ $initial }}</span>
                <div class="min-w-0">
                    <p class="font-semibold text-zinc-900 truncate">{{ $user->name }}</p>
                    <p class="text-sm text-zinc-500 truncate">{{ $user->email }}</p>
                </div>
            </div>

            <dl class="mt-4 pt-4 border-t border-zinc-200 space-y-3 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-zinc-500">{{ __('portal.profile.side.status') }}</dt>
                    <dd>
                        @if ($isVerified)
                            <span class="pill bg-emerald-50 text-emerald-700 text-xs py-0.5">
                                <x-app.icon name="check" class="size-3.5 shrink-0" />{{ __('portal.profile.side.verified') }}
                            </span>
                        @else
                            <span class="pill bg-amber-50 text-amber-800 text-xs py-0.5">
                                <x-app.icon name="clock" class="size-3.5 shrink-0" />{{ __('portal.profile.side.unverified') }}
                            </span>
                        @endif
                    </dd>
                </div>

                @if ($memberSince)
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-zinc-500">{{ __('portal.profile.side.member_since') }}</dt>
                        <dd class="text-zinc-900">{{ $memberSince }}</dd>
                    </div>
                @endif

                <div class="flex items-center justify-between gap-3 min-w-0">
                    <dt class="text-zinc-500 shrink-0">{{ __('portal.profile.side.workspace') }}</dt>
                    <dd class="text-zinc-900 truncate">{{ $workspaceName }}</dd>
                </div>
            </dl>

            {{-- Die Rechnungsanschrift gehoert dem Workspace, nicht dem
                 Benutzer -- sie steht deshalb nicht in diesem Formular. --}}
            <p class="mt-4 pt-4 border-t border-zinc-200 text-xs text-zinc-500">
                {{ __('portal.profile.side.address_hint') }}
                <a href="{{ $settingsUrl }}" class="text-brand hover:underline">{{ __('portal.menu.settings') }}</a>
            </p>
        </x-app.card>

    </div>

</div>
