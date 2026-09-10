{{--
    Impressum nach Paragraf 5 DDG.

    Alle Angaben stammen aus den Rechnungseinstellungen im Admin und werden von
    App\Services\CompanyProfile geliefert. Hier steht bewusst kein Platzhalter:
    Fehlen die Pflichtangaben, ist die Seite nicht erreichbar (siehe Route).
--}}
<x-layouts.portal>

    <x-slot name="title">
        {{ __('Impressum') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Anbieterkennzeichnung nach Paragraf 5 DDG.') }}
    </x-slot>

    <section class="section">
        <div class="container-portal max-w-3xl">
            <h1 class="text-2xl font-semibold text-zinc-900 mb-6">{{ __('Impressum') }}</h1>

            <div class="card p-6 space-y-6 text-zinc-600 leading-relaxed">
                <div>
                    <h2 class="font-semibold text-zinc-900 mb-2">{{ __('Anbieter') }}</h2>
                    <p>{{ $company->name() }}</p>
                    <p class="whitespace-pre-line">{{ $company->address() }}</p>
                </div>

                <div>
                    <h2 class="font-semibold text-zinc-900 mb-2">{{ __('Kontakt') }}</h2>
                    @if ($company->phone())
                        <p>{{ __('Telefon') }}: {{ $company->phone() }}</p>
                    @endif
                    <p>{{ __('E-Mail') }}: <a class="hover:text-zinc-900" href="mailto:{{ $company->email() }}">{{ $company->email() }}</a></p>
                </div>

                @if ($company->registrationNumber() || $company->vatId())
                    <div>
                        <h2 class="font-semibold text-zinc-900 mb-2">{{ __('Registerangaben') }}</h2>
                        @if ($company->registrationNumber())
                            <p>{{ __('Registernummer') }}: {{ $company->registrationNumber() }}</p>
                        @endif
                        @if ($company->vatId())
                            <p>{{ __('Umsatzsteuer-Identifikationsnummer') }}: {{ $company->vatId() }}</p>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </section>

</x-layouts.portal>
