<x-layouts.portal-focus>

    <x-slot name="title">
        {{ __('Login') }}
    </x-slot>

    {{-- Farbband, in das die Karte hineinragt -- wie auf der Startseite. --}}
    <div class="bg-brand h-40 md:h-52"></div>

    <div class="max-w-md mx-auto px-4 -mt-28 md:-mt-36 pb-12">

        @if (session('status'))
            <div class="rounded-2xl bg-white/15 p-4 flex items-start gap-3 mb-4 text-sm text-white">
                <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                {{ session('status') }}
            </div>
        @endif

        <div class="card shadow-lg p-5 md:p-8">
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900">{{ __('Login') }}</h1>
            <p class="text-zinc-500 mt-1">{{ __('Welcome back.') }}</p>

            @if ($isOtpLoginEnabled)
                {{-- Anmeldung per Einmalcode: eigene Livewire-Strecke, gerahmt
                     von derselben Karte. --}}
                <div class="mt-6">
                    <livewire:auth.login.one-time-password-login />
                </div>
            @else
                @include('auth.partials.traditional-login-form')
            @endif
        </div>

        <p class="text-sm text-zinc-500 text-center mt-6">
            {{ __('No account?') }}
            <a href="{{ route('register') }}" class="text-brand font-medium hover:underline">{{ __('Register') }}</a>
        </p>
    </div>

</x-layouts.portal-focus>
