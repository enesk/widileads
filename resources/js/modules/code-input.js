/*
 | Die sechs Kaestchen eines Bestaetigungscodes (LP-ONBOARD-005).
 |
 | Loest resources/js/modules/phone-verify.js ab: dasselbe Verhalten plus die
 | beiden Dinge, die der Telefon-Screen braucht -- die Pruefung startet von
 | selbst bei der sechsten Ziffer, und ein falscher Code schuettelt die Felder
 | und leert sie. Zwei Module waeren hier nicht zwei Zustaendigkeiten, sondern
 | zwei Mal dieselben Dokument-Handler auf derselben Klasse .code-box.
 |
 | Der zusammengesetzte Wert wandert in das verborgene Feld daneben -- nur
 | dieses haengt am Zustand der Livewire-Komponente. Sechs einzelne Felder an
 | den Zustand zu binden hiesse, bei jeder Ziffer eine Anfrage zu stellen.
 | Gebunden ist dort wire:model ohne .live: Das eingetragene input-Ereignis
 | zieht Livewires Zustand nach, ohne den Server zu fragen.
 |
 | Die Handler haengen am Dokument und nicht an den Feldern: Livewire tauscht
 | das Markup bei jedem Schrittwechsel aus, direkt gebundene Handler waeren
 | danach weg.
 */

/**
 * Der Wert, mit dem die Gruppe zuletzt abgeschickt wurde. Ohne diese Notiz
 * schickt jede weitere Ziffer in einem schon vollen Feld erneut ab -- und wer
 * eine Ziffer korrigiert, loest zwei Pruefungen aus.
 *
 * @type {WeakMap<HTMLElement, string>}
 */
const submitted = new WeakMap();

export function setUpCodeInput() {
    document.addEventListener('input', (event) => {
        const box = boxOf(event.target);

        if (box === null) {
            return;
        }

        const digits = box.value.replace(/\D/g, '');

        /*
         | Mehr als eine Ziffer in einem Kaestchen heisst: Die Eingabe kam am
         | Stueck. iOS und Android fuellen den Code aus der SMS programmatisch
         | -- der ganze Code landet im ersten Feld und gemeldet wird input
         | (inputType: insertReplacementText), kein paste. Verteilt wird
         | deshalb hier genauso wie im paste-Zweig.
         */
        if (digits.length > 1) {
            spread(box, digits);

            return;
        }

        box.value = digits.slice(-1);

        const boxes = boxesOf(box);
        const next = boxes[boxes.indexOf(box) + 1];

        if (box.value && next) {
            next.focus();
        }

        publish(box);
    });

    document.addEventListener('keydown', (event) => {
        const box = boxOf(event.target);

        if (box === null || event.key !== 'Backspace' || box.value !== '') {
            return;
        }

        const boxes = boxesOf(box);
        const previous = boxes[boxes.indexOf(box) - 1];

        if (previous) {
            previous.focus();
        }
    });

    document.addEventListener('paste', (event) => {
        const box = boxOf(event.target);

        if (box === null) {
            return;
        }

        const digits = (event.clipboardData?.getData('text') ?? '').replace(/\D/g, '');

        if (digits === '') {
            return;
        }

        /*
         | Auf genau sechs Ziffern zu bestehen hiesse: Wer die ganze SMS
         | kopiert ("Dein Code 123456, gueltig 10 Minuten"), fuegt acht
         | Ziffern ein und der Browser laesst eine davon stehen -- ohne
         | Meldung. Eingesetzt werden die ersten Ziffern, so viele wie
         | Kaestchen frei sind, der Rest faellt weg.
         */
        event.preventDefault();
        spread(box, digits);
    });

    /*
     | Der Code war falsch oder ist abgelaufen: Felder leeren, schuetteln,
     | Fokus auf das erste Kaestchen. Gemeldet wird das von der Livewire-
     | Komponente ($this->dispatch('code-input-reset')) und nicht hier
     | entschieden -- ob ein Code stimmt, weiss nur der Server.
     */
    window.addEventListener('code-input-reset', () => {
        document.querySelectorAll('[data-code-boxes]').forEach((group) => {
            reset(group);
        });
    });

    /*
     | Der Code-Screen ist eben erschienen: Fokus auf das erste Kaestchen.
     | Vollstaendig per Tastatur bedienbar heisst nicht nur "erreichbar",
     | sondern dass der Fokus beim Zustandswechsel dort steht, wo weiter
     | getippt wird. Gemeldet wird das von der Livewire-Komponente, nachdem
     | das Markup getauscht ist.
     */
    window.addEventListener('code-screen-entered', () => {
        // Eine Runde warten: Livewire meldet das Ereignis, bevor der Browser
        // das neue Markup gezeichnet hat.
        requestAnimationFrame(() => {
            const first = document.querySelector('[data-code-boxes] .code-box');

            if (first instanceof HTMLInputElement) {
                first.focus();
            }

            awaitSmsCode();
        });
    });

    /*
     | Das Verlassen der Seite bricht eine offene WebOTP-Anfrage ab. Ohne das
     | bleibt der Dialog von Chrome am Leben, waehrend die Reihe, in die er
     | schreiben wollte, schon weg ist.
     */
    window.addEventListener('pagehide', abortSmsCode);

    // Der Code-Screen kann schon stehen, wenn dieses Modul anlaeuft -- etwa
    // nach einem Neuladen mitten im Vorgang. Dann gibt es kein
    // code-screen-entered mehr, auf das zu warten waere.
    awaitSmsCode();
}

/**
 * Die offene WebOTP-Anfrage -- es gibt nie mehr als eine.
 *
 * @type {AbortController|null}
 */
let smsCodeRequest = null;

/**
 * Laesst Chrome den Code aus der SMS anbieten (WebOTP).
 *
 * Das ist der Android-Weg: `autocomplete="one-time-code"` wertet nur Safari
 * aus, Chrome fuer Android kennt als Autofill-Quelle ausschliesslich diese
 * API. Sie zeigt einen eigenen Dialog ("Code aus SMS verwenden?"), und nur
 * wenn die SMS als letzte Zeile `@<host> #<code>` traegt -- derselbe Host wie
 * die Seite. Fehlt die Zeile, loest das Versprechen nie ein; angezeigt wird
 * dann nichts, und getippt werden kann die ganze Zeit weiter.
 *
 * Deshalb ist hier auch jeder Fehler stumm: Die API ist eine Abkuerzung, kein
 * Pflichtweg. Ein Abbruch des Nutzers (AbortError), eine SMS ohne die Zeile
 * und ein Browser ohne die API sehen von aussen gleich aus -- die Reihe
 * bleibt leer und wartet auf die Tastatur.
 *
 * Gefuellt wird ueber dieselbe spread()-Funktion wie Autofill und Einfuegen:
 * Damit startet die Pruefung bei der sechsten Ziffer von selbst, ohne dass
 * daneben ein zweiter Fuellweg entsteht.
 */
function awaitSmsCode() {
    // `isSecureContext` statt eines Blicks auf das Protokoll: localhost ist
    // ebenfalls sicher, und nur dort gibt der Browser die API ueberhaupt her.
    if (!('OTPCredential' in window) || !window.isSecureContext) {
        return;
    }

    /*
     | Nur Reihen, die von selbst abschicken duerfen (data-code-submit). Die
     | Caller-Id-Bestaetigung traegt das Attribut nicht: Dort sagt ein Anruf
     | den Code an, eine SMS kommt gar nicht -- ein Dialog waere dort falsch.
     */
    const box = document.querySelector('[data-code-boxes][data-code-submit] .code-box');

    if (!(box instanceof HTMLInputElement)) {
        return;
    }

    // Eine neue Anfrage loest die alte ab: Nach "Code erneut senden" steht
    // dasselbe Ereignis noch einmal an, und zwei offene Anfragen wuerden sich
    // gegenseitig ablehnen.
    abortSmsCode();

    const request = new AbortController();
    smsCodeRequest = request;

    navigator.credentials
        .get({ otp: { transport: ['sms'] }, signal: request.signal })
        .then((credential) => {
            const digits = (credential?.code ?? '').replace(/\D/g, '');

            // Die Reihe von eben kann inzwischen ausgetauscht sein -- Livewire
            // zeichnet bei jedem Schrittwechsel neu. Gesucht wird deshalb
            // erneut, statt die alte Referenz zu fuellen.
            const first = document.querySelector('[data-code-boxes][data-code-submit] .code-box');

            if (digits === '' || !(first instanceof HTMLInputElement)) {
                return;
            }

            spread(first, digits);
        })
        .catch(() => {
            // Stumm, siehe oben.
        })
        .finally(() => {
            if (smsCodeRequest === request) {
                smsCodeRequest = null;
            }
        });
}

/**
 * Bricht eine offene WebOTP-Anfrage ab.
 */
function abortSmsCode() {
    smsCodeRequest?.abort();
    smsCodeRequest = null;
}

/**
 * Verteilt mehrere Ziffern ab dem angefassten Kaestchen der Reihe nach.
 *
 * Ueberzaehlige Ziffern fallen weg -- mehr Kaestchen als die Reihe hat gibt
 * es nicht. Danach steht der Fokus hinter der letzten eingesetzten Ziffer
 * (ist die Reihe voll: auf dem letzten Kaestchen) und der Code geht denselben
 * publish()-Weg wie eine einzeln getippte Ziffer, damit die Pruefung bei
 * sechs Ziffern von selbst startet.
 *
 * @param {HTMLInputElement} box
 * @param {string} digits
 */
function spread(box, digits) {
    const boxes = boxesOf(box);
    const start = boxes.indexOf(box);

    if (start === -1) {
        return;
    }

    const fitting = digits.slice(0, boxes.length - start);

    Array.from(fitting).forEach((digit, offset) => {
        boxes[start + offset].value = digit;
    });

    const last = start + fitting.length;

    (boxes[last] ?? boxes[boxes.length - 1]).focus();

    publish(box);
}

/**
 * Leert eine Gruppe und schuettelt sie einmal.
 *
 * Die Bewegung haengt an einer Klasse, die nach dem Lauf wieder abfaellt --
 * sonst schuettelt beim zweiten Fehlversuch nichts mehr. Wer Bewegung
 * abbestellt hat, bekommt sie nicht: Das uebernimmt die Media-Query in
 * resources/css/components/code-input.css, hier steht keine zweite Abfrage
 * desselben Wunsches.
 *
 * @param {Element} group
 */
function reset(group) {
    const boxes = Array.from(group.querySelectorAll('.code-box'));

    boxes.forEach((field) => {
        field.value = '';
    });

    submitted.delete(group);
    publishValue(group, '');

    group.classList.remove('code-input--shake');
    // Ein Reflow zwischen Weg und Wieder-Dran: Ohne ihn fasst der Browser
    // beides zu "keine Aenderung" zusammen und die Bewegung bleibt aus.
    void group.offsetWidth;
    group.classList.add('code-input--shake');

    boxes[0]?.focus();
}

/**
 * Das Kaestchen zum Ereignis -- oder null, wenn das Ereignis woanders herkommt.
 *
 * @param {EventTarget|null} target
 * @returns {HTMLInputElement|null}
 */
function boxOf(target) {
    if (!(target instanceof HTMLInputElement) || !target.classList.contains('code-box')) {
        return null;
    }

    return target.closest('[data-code-boxes]') === null ? null : target;
}

/**
 * @param {HTMLInputElement} box
 * @returns {HTMLInputElement[]}
 */
function boxesOf(box) {
    const group = box.closest('[data-code-boxes]');

    return group === null ? [] : Array.from(group.querySelectorAll('.code-box'));
}

/**
 * Schreibt den zusammengesetzten Code in das verborgene Feld und meldet die
 * Aenderung -- erst dadurch sieht Livewire sie. Ist der Code vollstaendig und
 * traegt die Gruppe data-code-submit, geht das Formular von selbst ab.
 *
 * @param {HTMLInputElement} box
 */
function publish(box) {
    const group = box.closest('[data-code-boxes]');

    if (group === null) {
        return;
    }

    const value = boxesOf(box).map((field) => field.value).join('');

    publishValue(group, value);

    if (value.length !== boxesOf(box).length || !group.hasAttribute('data-code-submit')) {
        return;
    }

    if (submitted.get(group) === value) {
        return;
    }

    submitted.set(group, value);

    /*
     | Abgeschickt wird das Formular und nicht eine Methode der Komponente:
     | Der Knopf "Bestaetigen" geht denselben Weg (wire:submit), damit sind
     | automatische und von Hand ausgeloeste Pruefung derselbe Vorgang -- samt
     | Ladezustand und Fehlerbehandlung.
     */
    const form = group.closest('form');

    if (form instanceof HTMLFormElement) {
        // Das letzte Kaestchen behaelt den Fokus; requestSubmit() loest
        // ausserdem die Pflichtfeld-Pruefung des Browsers mit aus, anders als
        // submit().
        form.requestSubmit();
    }
}

/**
 * Das verborgene Feld zur Gruppe.
 *
 * Gesucht wird im Formular und erst ersatzweise beim direkten Elternteil: Die
 * sechs Kaestchen stehen je nach Screen in einem <fieldset> oder frei, und das
 * verborgene Feld liegt daneben, nicht darin. Eine Suche, die nur das
 * Elternteil kennt, findet es im ersten Fall nicht.
 *
 * @param {Element} group
 * @param {string} value
 */
function publishValue(group, value) {
    const scope = group.closest('form') ?? group.parentElement;
    const target = scope?.querySelector('[data-code-value]');

    if (!(target instanceof HTMLInputElement)) {
        return;
    }

    target.value = value;
    target.dispatchEvent(new Event('input', { bubbles: true }));
}
