/*
 | Gefuehrte Touren (LP-ONBOARD-013).
 |
 | Verdunkelt den Bildschirm, hebt drei bis vier Elemente der Seite einzeln
 | hervor und erklaert sie mit einer Sprechblase. Zwei Touren gibt es:
 | "marketplace" und "lead_detail". Beide laufen genau einmal, danach nie wieder
 | -- erneut startbar bleiben sie ueber das Kontomenue.
 |
 | Die Schritte stehen NICHT hier. Sie stehen als data-Attribute am Markup:
 |
 |   <div data-tour="marketplace:1"
 |        data-tour-title="Dein Guthaben"
 |        data-tour-text="Hier siehst du, wofuer dein Guthaben reicht.">
 |
 | Die Zahl ist die Reihenfolge, optional kommen data-tour-side ("top", "right",
 | "bottom", "left") und data-tour-align ("start", "center", "end") dazu. Der
 | Grund fuer diesen Umweg: Eine Schrittliste im Skript enthaelt CSS-Selektoren
 | auf fremdes Markup und bricht still, sobald eine Ansicht umgebaut wird --
 | genau dann, wenn niemand ans Skript denkt. Am Markup faellt eine verwaiste
 | Marke beim Umbau auf.
 |
 | Was gezeigt wird, sagt der Rahmen ueber x-app.tour (ein verstecktes Element
 | mit data-tour-config): Name der Tour, ob sie von selbst startet, wohin der
 | Abschluss gemeldet wird, die Beschriftungen und wie viele Marken die Seite
 | tragen muss. Fehlt eine davon -- leerer Marktplatz, also keine Lead-Karte --,
 | startet die Tour stillschweigend nicht und wird beim naechsten Besuch erneut
 | versucht. Eine halbe Tour ist schlimmer als keine.
 |
 | driver.js kommt aus npm und wird erst beim Start geladen (dynamisches
 | import): Nur zwei Portalseiten tragen eine Tour, und die anderen sollen die
 | Bibliothek nicht mitschleppen.
 */

/*
 | Das Grundstylesheet der Bibliothek: Es traegt die Lage der Sprechblase und
 | des Pfeils. Statisch eingebunden und nicht im dynamischen Teil, damit das
 | Aussehen nicht erst einen Wimpernschlag nach der Verdunkelung steht.
 | Ueberschrieben wird es in resources/css/components/tour.css.
 */
import 'driver.js/dist/driver.css';

/**
 * Haengt sich an die Tour-Konfiguration der Seite, falls es eine gibt.
 */
export function setUpTour() {
    const config = document.querySelector('[data-tour-config]');

    if (config === null) {
        return;
    }

    const name = config.dataset.tourName ?? '';
    const expected = Number.parseInt(config.dataset.tourSteps ?? '0', 10);

    if (name === '') {
        return;
    }

    // Der Eintrag im Kontomenue ist im Markup versteckt: Ob die Seite eine Tour
    // hat, weiss erst dieses Skript -- und ein Eintrag, der nichts tut, ist
    // schlimmer als keiner. Deshalb erscheint er nur, wenn die Marken auch da
    // sind; auf einem leeren Marktplatz bleibt er weg.
    if (readSteps(name).length < Math.max(expected, 1)) {
        return;
    }

    document.querySelectorAll('[data-tour-restart]').forEach((button) => {
        button.hidden = false;
        button.addEventListener('click', () => {
            // Das offene Menue zuklappen, bevor die Verdunkelung kommt: Es liegt
            // sonst ueber dem Spotlight und nimmt keine Klicks mehr an.
            document
                .querySelector('[data-dropdown-toggle][aria-expanded="true"]')
                ?.click();

            startTour(config, name, expected);
        });
    });

    if (shouldStartAutomatically(config, name)) {
        startTour(config, name, expected);
    }
}

/**
 * Startet die Tour von selbst? Entweder weil die Adresse danach fragt
 * (?tour=marketplace) oder weil der Rahmen sagt, dass dieser Workspace sie noch
 * nicht gesehen hat.
 *
 * @param {HTMLElement} config
 * @param {string} name
 */
function shouldStartAutomatically(config, name) {
    const requested = new URLSearchParams(window.location.search).get('tour');

    if (requested === name) {
        /*
         | Den Parameter aus der Adresse nehmen, sonst startet die Tour bei jedem
         | Neuladen erneut -- und steht am Ende in jedem Lesezeichen.
         */
        const url = new URL(window.location.href);
        url.searchParams.delete('tour');
        window.history.replaceState({}, '', url);

        return true;
    }

    return config.hasAttribute('data-tour-auto');
}

/**
 * Liest die Schritte aus dem Markup und uebergibt sie an driver.js.
 *
 * @param {HTMLElement} config
 * @param {string} name
 * @param {number} expected  so viele Marken muss die Seite tragen
 */
async function startTour(config, name, expected) {
    const steps = readSteps(name);

    if (steps.length === 0 || steps.length < expected) {
        return;
    }

    const { driver } = await import('driver.js');

    /*
     | Bewegung nur, wenn der Nutzer sie will. Die Vorliebe wird hier gelesen
     | und nicht nur im CSS beantwortet: Das Ein- und Ausblenden steckt in der
     | Bibliothek, und das sanfte Scrollen ist Javascript.
     */
    const calm = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const labels = readLabels(config);

    const tour = driver({
        steps,
        animate: !calm,
        smoothScroll: !calm,
        allowClose: true,
        // Tippen auf die verdunkelte Flaeche beendet die Tour -- derselbe Weg,
        // den auch die Blaetter des Portals nehmen.
        overlayClickBehavior: 'close',
        allowKeyboardControl: true,
        // Das hervorgehobene Element bleibt waehrend der Tour unantastbar: Ein
        // Klick auf "Lead kaufen" mitten in der Erklaerung reserviert sonst
        // Geld, bevor der Satz dazu gelesen ist.
        disableActiveInteraction: true,
        overlayColor: cssValue('--tour-overlay-color', '#18181b'),
        overlayOpacity: Number.parseFloat(cssValue('--tour-overlay-opacity', '0.55')),
        stagePadding: Number.parseFloat(cssValue('--tour-stage-padding', '8')),
        stageRadius: Number.parseFloat(cssValue('--tour-stage-radius', '12')),
        popoverClass: 'tour-popover',
        showProgress: steps.length > 1,
        progressText: labels.progress,
        nextBtnText: labels.next,
        prevBtnText: labels.previous,
        doneBtnText: labels.done,
        // Das Kreuz oben rechts entfaellt: Der Ausstieg steht als Knopf in der
        // Fusszeile und sagt, was er tut.
        showButtons: ['next', 'previous'],
        onPopoverRender: (popover, { driver: instance }) => {
            addSkipButton(popover, instance, labels.close);

            // Auf dem ersten Schritt gibt es kein Zurueck. driver.js zeigt den
            // Knopf trotzdem und schaltet ihn nur grau -- auf dem Telefon nimmt
            // er damit die halbe Knopfzeile fuer nichts.
            popover.previousButton.style.display = instance.isFirstStep() ? 'none' : '';
        },
        onDestroyed: () => {
            reportSeen(config);
        },
    });

    tour.drive();
}

/**
 * Die Schritte dieser Tour, nach ihrer Nummer sortiert.
 *
 * Marken ohne Flaeche fallen heraus: Ein zugeklapptes oder verstecktes Element
 * laesst sich nicht hervorheben, und der Spotlight stuende auf einem Punkt.
 *
 * @param {string} name
 */
function readSteps(name) {
    const prefix = `${name}:`;

    return Array.from(document.querySelectorAll('[data-tour]'))
        .map((element) => ({
            element,
            order: Number.parseInt((element.getAttribute('data-tour') ?? '').slice(prefix.length), 10),
            marker: element.getAttribute('data-tour') ?? '',
        }))
        .filter(({ marker, order, element }) =>
            marker.startsWith(prefix) && Number.isFinite(order) && element.getClientRects().length > 0
        )
        .sort((a, b) => a.order - b.order)
        .map(({ element }) => ({
            element,
            popover: {
                title: element.dataset.tourTitle ?? '',
                description: element.dataset.tourText ?? '',
                side: element.dataset.tourSide || undefined,
                align: element.dataset.tourAlign || undefined,
            },
        }));
}

/**
 * Die Beschriftungen der Sprechblase. Sie kommen uebersetzt aus dem Rahmen --
 * im Skript steht kein deutscher Text.
 *
 * @param {HTMLElement} config
 */
function readLabels(config) {
    return {
        next: config.dataset.tourNext ?? '',
        previous: config.dataset.tourPrevious ?? '',
        done: config.dataset.tourDone ?? '',
        close: config.dataset.tourClose ?? '',
        progress: config.dataset.tourProgress ?? '',
    };
}

/**
 * Setzt den Ausstieg als Knopf in die Fusszeile der Sprechblase.
 *
 * Auf dem letzten Schritt nicht: Dort steht "Fertig", und zwei Knoepfe, die
 * dasselbe tun, lesen sich wie zwei Entscheidungen.
 *
 * @param {object} popover  die Teile der Sprechblase, von driver.js gebaut
 * @param {object} instance die laufende Tour
 * @param {string} label
 */
function addSkipButton(popover, instance, label) {
    if (label === '' || instance.isLastStep()) {
        return;
    }

    const skip = document.createElement('button');
    skip.type = 'button';
    skip.className = 'tour-skip';
    skip.textContent = label;
    skip.addEventListener('click', () => instance.destroy());

    /*
     | Ans Ende des Dokuments, aber per CSS nach links: driver.js setzt den
     | Fokus auf das erste anfassbare Element der Sprechblase, und das soll
     | "Weiter" sein und nicht der Ausstieg -- sonst beendet die Eingabetaste
     | die Tour, die der Kaeufer gerade erst geoeffnet hat.
     */
    popover.footer.appendChild(skip);
}

/**
 * Meldet dem Server, dass diese Tour gesehen wurde -- bei Abschluss genauso wie
 * bei "Ueberspringen", Escape und Tippen daneben. Alle vier heissen dasselbe:
 * Der Kaeufer will sie nicht wieder vorgesetzt bekommen.
 *
 * Fehler bleiben still: Eine Tour, die am Ende eine Fehlermeldung zeigt, ist
 * schlimmer als eine, die beim naechsten Besuch noch einmal startet.
 *
 * @param {HTMLElement} config
 */
function reportSeen(config) {
    const url = config.dataset.tourSeenUrl ?? '';
    const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    if (url === '') {
        return;
    }

    fetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': token,
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json',
        },
        // Damit die Meldung auch durchgeht, wenn der Kaeufer direkt nach dem
        // letzten Schritt weiterklickt.
        keepalive: true,
    }).catch(() => {});
}

/**
 * Ein Token aus den Portal-Variablen. Masse und Farben der Verdunkelung stehen
 * in resources/css/components/tour.css und damit bei den uebrigen Tokens --
 * driver.js nimmt sie aber nur als Wert an.
 *
 * @param {string} name
 * @param {string} fallback
 */
function cssValue(name, fallback) {
    const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();

    return value === '' ? fallback : value;
}
