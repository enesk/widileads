/*
 | Kurzmeldungen des Portals ("Toaster").
 |
 | Ein Toast sagt: "Es ist angekommen." Nicht mehr. Er steht kurz unten rechts,
 | verschwindet von selbst und nimmt keinen Platz im Aufbau weg -- anders als
 | ein Band ueber dem Formular, das die Seite jedes Mal um seine Hoehe
 | verschiebt.
 |
 | Zwei Wege hinein, ein Weg hinaus:
 |
 |   1. Aus einer Livewire-Komponente: $this->dispatch('toast', message: '…',
 |      level: 'success'). Livewire gibt das als Browser-Ereignis weiter, hier
 |      wird es aufgefangen.
 |   2. Aus einer gewoehnlichen Anfrage: session()->flash('toast', [...]). Das
 |      Blade rendert den Toast dann bereits fertig mit, dieses Skript haengt
 |      sich nur noch mit Schliessen und Ablauf daran.
 |
 | Bewusst kein Alpine und kein Inline-Handler: Das Portal laeuft sonst auch
 | ohne beides (siehe Kopf von portal.js).
 */

/** So lange steht ein Toast, bevor er von selbst geht. */
const LIFETIME_MS = 5000;

/*
 | Der Kasten heisst .toast-item und nicht .toast: daisyUI belegt .toast
 | bereits und gewinnt aus einer spaeteren Kaskadenschicht.
 */

/** Mehr als das steht nie gleichzeitig -- sonst verdeckt der Stapel die Seite. */
const MAX_VISIBLE = 3;

const LEVELS = ['success', 'warning', 'danger', 'info'];

export function setUpToasts() {
    const region = document.querySelector('[data-toast-region]');

    if (region === null) {
        return;
    }

    // Serverseitig gerenderte Toasts (Flash-Meldungen) leben schon im DOM.
    region.querySelectorAll('[data-toast]').forEach((toast) => watch(toast));

    // Aus Livewire. Der Name ist absichtlich schlicht: jede Komponente, die
    // etwas gespeichert hat, kann ihn ohne Absprache benutzen.
    window.addEventListener('toast', (event) => {
        const detail = event.detail ?? {};

        show(region, {
            message: detail.message ?? '',
            title: detail.title ?? null,
            level: LEVELS.includes(detail.level) ? detail.level : 'success',
        });
    });

    // Schliessen. Am Bereich gebunden statt am einzelnen Knopf, weil Toasts
    // erst spaeter entstehen.
    region.addEventListener('click', (event) => {
        const button = event.target.closest('[data-toast-dismiss]');

        if (button !== null) {
            dismiss(button.closest('[data-toast]'));
        }
    });
}

/**
 * Baut einen Toast und haengt ihn oben in den Stapel.
 */
function show(region, { message, title, level }) {
    if (message === '') {
        return;
    }

    const toast = document.createElement('div');
    toast.setAttribute('data-toast', level);
    toast.setAttribute('role', level === 'danger' ? 'alert' : 'status');
    toast.className = `toast-item toast-${level}`;

    const icon = document.createElement('span');
    icon.className = 'toast-icon';
    icon.setAttribute('aria-hidden', 'true');
    icon.innerHTML = iconFor(level);

    const body = document.createElement('div');
    body.className = 'toast-body';

    if (title !== null && title !== '') {
        const heading = document.createElement('p');
        heading.className = 'toast-title';
        heading.textContent = title;
        body.append(heading);
    }

    const text = document.createElement('p');
    text.className = 'toast-text';
    // textContent, nicht innerHTML: Eine Meldung kann den Namen eines Leads
    // oder eine Eingabe des Benutzers enthalten.
    text.textContent = message;
    body.append(text);

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'toast-dismiss';
    close.setAttribute('data-toast-dismiss', '');
    close.setAttribute('aria-label', region.dataset.toastDismissLabel ?? 'Schließen');
    close.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>';

    toast.append(icon, body, close);
    region.prepend(toast);

    trim(region);
    watch(toast);
}

/**
 * Laesst einen Toast nach seiner Zeit gehen. Der Zeitgeber haelt an, solange
 * der Zeiger darauf liegt oder der Schliessknopf den Fokus hat: Wer gerade
 * liest, soll nicht mitten im Satz seinen Text verlieren.
 */
function watch(toast) {
    if (toast === null || toast.dataset.toastWatched === 'ja') {
        return;
    }

    toast.dataset.toastWatched = 'ja';

    let timer = null;

    const start = () => {
        timer = window.setTimeout(() => dismiss(toast), LIFETIME_MS);
    };

    const stop = () => {
        window.clearTimeout(timer);
    };

    toast.addEventListener('mouseenter', stop);
    toast.addEventListener('mouseleave', start);
    toast.addEventListener('focusin', stop);
    toast.addEventListener('focusout', start);

    start();
}

function dismiss(toast) {
    if (toast === null) {
        return;
    }

    // Wer weniger Bewegung eingestellt hat, bekommt sie nicht.
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        toast.remove();

        return;
    }

    toast.classList.add('toast-leaving');
    toast.addEventListener('transitionend', () => toast.remove(), { once: true });

    // Sicherheitsnetz: Bleibt das Ereignis aus (unsichtbarer Tab), geht der
    // Toast trotzdem.
    window.setTimeout(() => toast.remove(), 500);
}

function trim(region) {
    const toasts = region.querySelectorAll('[data-toast]');

    for (let index = MAX_VISIBLE; index < toasts.length; index++) {
        toasts[index].remove();
    }
}

function iconFor(level) {
    const paths = {
        success: '<path d="M20 6 9 17l-5-5"/>',
        warning: '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4M12 17h.01"/>',
        danger: '<circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>',
        info: '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
    };

    return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${paths[level] ?? paths.info}</svg>`;
}
