{{--
    Der Platz, an dem Kurzmeldungen erscheinen.

    Steht genau einmal im Rahmen (portal-app.blade.php), nicht je Seite. Der
    Bereich ist meistens leer -- gefuellt wird er aus zwei Richtungen:

      * aus einer Livewire-Komponente mit $this->toast('Gespeichert.'),
        siehe App\Livewire\Portal\Concerns\ShowsToasts
      * aus einer gewoehnlichen Anfrage mit
        session()->flash('toast', ['message' => '…', 'level' => 'success'])

    Der Flash-Fall wird hier serverseitig gerendert: Eine Meldung nach einer
    Weiterleitung muss auch dann stehen, wenn das Skript noch nicht geladen ist.
    resources/js/modules/toasts.js haengt sich anschliessend mit Schliessen und
    Ablauf daran.

    aria-live="polite" statt "assertive": Eine Bestaetigung darf warten, bis der
    Screenreader den laufenden Satz beendet hat.
--}}
@php
    $flash = session('toast');

    // Bequemlichkeit fuer Controller, die nur einen Satz haben.
    if (is_string($flash)) {
        $flash = ['message' => $flash, 'level' => 'success'];
    }

    $level = in_array($flash['level'] ?? 'success', ['success', 'warning', 'danger', 'info'], true)
        ? $flash['level'] ?? 'success'
        : 'success';
@endphp

<div
    class="toast-region"
    data-toast-region
    data-toast-dismiss-label="{{ __('portal.toast.dismiss') }}"
    aria-live="polite"
    aria-atomic="false"
>
    @if (is_array($flash) && ($flash['message'] ?? '') !== '')
        <div data-toast="{{ $level }}" role="{{ $level === 'danger' ? 'alert' : 'status' }}" class="toast-item toast-{{ $level }}">
            <span class="toast-icon" aria-hidden="true">
                <x-app.icon :name="match ($level) {
                    'success' => 'check',
                    'warning' => 'alert',
                    'danger' => 'alert-circle',
                    default => 'info',
                }" />
            </span>

            <div class="toast-body">
                @if (($flash['title'] ?? null) !== null)
                    <p class="toast-title">{{ $flash['title'] }}</p>
                @endif
                <p class="toast-text">{{ $flash['message'] }}</p>
            </div>

            <button type="button" class="toast-dismiss" data-toast-dismiss aria-label="{{ __('portal.toast.dismiss') }}">
                <x-app.icon name="close" />
            </button>
        </div>
    @endif
</div>
