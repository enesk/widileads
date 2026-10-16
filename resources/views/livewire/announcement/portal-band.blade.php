{{--
    Das Ankuendigungsband des Portals (#95, #105).

    Startseite und angemeldeter Arbeitsbereich tragen dasselbe Innenleben --
    zwei Formen fuer dieselbe Sache haetten nichts gewonnen. Unterschiedlich
    sind nur Flaeche und Polsterung ($wrapperClass, $innerClass): auf den
    oeffentlichen Seiten eine randlose Flaeche unter dem Kopf mit der
    Polsterung im Container, im Arbeitsbereich eine gerundete Karte im
    Inhaltsbereich. Beides kommt von der Einbindung, hier steht keine
    Verzweigung nach Platzierung.

    Der Kasten des Starterkits (livewire/announcement/view.blade.php, voll
    gefaerbt ueber die ganze Breite) bleibt dem SaaSykit-Kopf von Blog und
    Roadmap. Im Portal waere er ein Alarm: Eine Ankuendigung darf den Hauptweg
    nicht ueberstrahlen -- auf der Startseite ist das die Anmeldung, im
    Arbeitsbereich der Marktplatz. Die Flaeche bleibt deshalb neutral (zinc),
    die Marke traegt nur das Symbol und die Verweise im Text.

    Langer Text wird nicht abgeschnitten, sondern umbrochen: Ein Verweis in der
    Ankuendigung waere sonst genau das, was wegfaellt. Die Textzelle endet bei
    max-w-3xl, die Flaeche laeuft weiter.

    role="region" statt role="status": Die Mitteilung steht beim Laden da und
    aendert sich nicht waehrend der Nutzung -- ein Live-Bereich wuerde sie
    Screenreadern vor den Hauptinhalt sprechen.

    Zeitfenster, Aktivschaltung und Platzierung entscheidet der
    AnnouncementService -- hier steht keine Zeile davon.

    Erwartete Daten: $announcement (aus App\Livewire\Announcement\View).
--}}
<div>
    @if ($announcement !== null)
        <div class="{{ $wrapperClass }}" x-data="{ isVisible: true }" x-show="isVisible">
            <div class="{{ $innerClass }}" role="region" aria-label="{{ __('portal.announcement.region') }}">
                <div class="flex items-start gap-2.5 sm:gap-3 text-sm text-zinc-700">
                    <span class="shrink-0 mt-px text-brand">
                        <x-app.icon name="info" class="size-5" />
                    </span>

                    <div class="min-w-0 flex-1 max-w-3xl break-words [&_a]:font-medium [&_a]:text-brand-700 [&_a]:underline">
                        {!! str($announcement->content)->sanitizeHtml() !!}
                    </div>

                    @if ($announcement->is_dismissible)
                        {{-- Geschlossen gilt fuer diese Browsersitzung: Die
                             Kennung landet in der Session (siehe dismiss()),
                             nach einer neuen Anmeldung ist das Band wieder da.
                             Eine Ankuendigung, die ein Klick fuer immer
                             entfernt, waere fuer "Wartung heute Abend" das
                             falsche Gedaechtnis.

                             Die Tastflaeche misst ueber before: 44 px, ohne die
                             Bandhoehe zu aendern: -m-1 nimmt die Polsterung
                             wieder heraus. --}}
                        <button
                            type="button"
                            wire:click="dismiss({{ $announcement->id }})"
                            x-on:click="isVisible = false;@if ($focusAfterDismiss !== '') $nextTick(() => document.querySelector('{{ $focusAfterDismiss }}')?.focus())@endif"
                            aria-label="{{ __('portal.announcement.dismiss') }}"
                            class="relative shrink-0 -m-1 p-1 rounded-md text-zinc-500 hover:text-zinc-900 hover:bg-zinc-200 focus-visible:outline-2 focus-visible:outline-brand-600 before:absolute before:-inset-2 before:content-['']"
                        >
                            <x-app.icon name="close" class="size-5" />
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
