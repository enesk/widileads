{{--
    Anmeldeformular im Portal-Stil.

    Gepruefft wird serverseitig; die Fehler stehen unter dem jeweiligen Feld.
    Eine zweite Pruefung im Browser waere nur eine Vorschau darauf und muesste
    bei jeder Regeländerung nachgezogen werden.
--}}
<form method="POST" action="{{ route('login') }}" class="mt-6 space-y-5">
    @csrf

    <div>
        <label for="email" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('Email Address') }}</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
               autocomplete="email" inputmode="email" placeholder="name@firma.de"
               @class(['input', 'border-red-500' => $errors->has('email')])>
        @error('email')
            <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <div class="flex items-center justify-between mb-1">
            <label for="password" class="text-sm font-medium text-zinc-700">{{ __('Password') }}</label>
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="text-sm text-brand hover:underline">
                    {{ __('Forgot Your Password?') }}
                </a>
            @endif
        </div>

        <div class="relative">
            <input id="password" type="password" name="password" required autocomplete="current-password"
                   @class(['input pr-12', 'border-red-500' => $errors->has('password')])>

            {{-- Umschalter fuer die Sichtbarkeit. Das Verhalten steht in
                 resources/js/portal.js, hier nur die Kennung des Feldes. --}}
            <button type="button"
                    data-password-toggle="password"
                    aria-label="{{ __('Show password') }}"
                    class="absolute right-1 top-1/2 -translate-y-1/2 size-9 rounded-lg text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100 flex items-center justify-center focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
        </div>

        @error('password')
            <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>

    @if (config('app.recaptcha_enabled'))
        <div>
            {!! htmlFormSnippet() !!}
            @error('g-recaptcha-response')
                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        @push('tail')
            {!! htmlScriptTagJsApi() !!}
        @endpush
    @endif

    <label class="flex items-center gap-2.5 text-sm text-zinc-700 cursor-pointer">
        <input type="checkbox" name="remember" class="size-5 rounded border-zinc-300 text-brand focus:ring-brand"
               {{ old('remember') ? 'checked' : '' }}>
        {{ __('Remember Me') }}
    </label>

    <button type="submit" class="btn-primary w-full">{{ __('Login') }}</button>
</form>

{{-- Andere Wege hinein, nur wenn welche eingerichtet sind. --}}
<x-auth.social-login class="mt-6">
    <x-slot name="before">
        <div class="flex items-center gap-3 mb-4">
            <span class="flex-1 border-t border-zinc-200"></span>
            <span class="text-xs text-zinc-400">{{ __('or') }}</span>
            <span class="flex-1 border-t border-zinc-200"></span>
        </div>
    </x-slot>
</x-auth.social-login>
