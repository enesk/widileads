{{-- FB-032: Lead-Detail. Bewusst schmal -- die Arbeitsoberflaechen bauen
     FB-034 (Betreiber) und FB-057 (Kaeufer) darauf auf. Der Kontaktblock ist
     bereits hier die einzige Stelle, an der Kontaktdaten ausgegeben werden. --}}
<x-layouts.app>
    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 px-4 py-6">
        <header>
            <h1 class="text-xl font-semibold">
                {{ __('leads.detail.heading', ['id' => $presenter->lead->id]) }}
            </h1>
            <p class="mt-1 text-sm text-base-content/70">
                {{ __('leads.detail.state', ['state' => $presenter->lead->lead_state->label()]) }}
            </p>
        </header>

        <x-lead.contact-card :presenter="$presenter" />
    </div>
</x-layouts.app>
