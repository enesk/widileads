/*
 | Die sechs Kaestchen des Bestaetigungscodes (Portal Phase 1).
 |
 | Auto-Weiterspringen, Ruecktaste zurueck, Einfuegen des ganzen Codes. Der
 | zusammengesetzte Wert wandert in das verborgene Feld daneben -- nur dieses
 | haengt am Zustand der Livewire-Komponente. Sechs einzelne Felder an den
 | Zustand zu binden hiesse, bei jeder Ziffer eine Anfrage zu stellen.
 |
 | Die Handler haengen am Dokument und nicht an den Feldern: Livewire tauscht
 | das Markup bei jedem Schrittwechsel aus, direkt gebundene Handler waeren
 | danach weg.
 */

export function setUpPhoneVerify() {
    document.addEventListener('input', (event) => {
        const box = boxOf(event.target);

        if (box === null) {
            return;
        }

        box.value = box.value.replace(/\D/g, '').slice(-1);

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
        const boxes = boxesOf(box);

        if (digits.length !== boxes.length) {
            return;
        }

        event.preventDefault();
        boxes.forEach((field, index) => {
            field.value = digits[index];
        });
        boxes[boxes.length - 1].focus();

        publish(box);
    });
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
 * Aenderung -- erst dadurch sieht Livewire sie.
 *
 * @param {HTMLInputElement} box
 */
function publish(box) {
    const group = box.closest('[data-code-boxes]');
    const target = group?.parentElement?.querySelector('[data-code-value]');

    if (!(target instanceof HTMLInputElement)) {
        return;
    }

    target.value = boxesOf(box).map((field) => field.value).join('');
    target.dispatchEvent(new Event('input', { bubbles: true }));
}
