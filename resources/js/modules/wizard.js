/*
 | Der Schrittwechsel im Einrichtungs-Wizard (LP-ONBOARD-007).
 |
 | Jeder Schritt ist eine eigene Adresse, ein Wechsel also ein Seitenaufbau.
 | Zwei Dinge muessen dabei von Hand passieren, die der Browser nicht
 | uebernimmt:
 |
 | 1. Der Fokus. Nach einer Navigation steht er am Seitenanfang. Wer mit der
 |    Tastatur oder einer Vorlesesoftware arbeitet, muesste sich durch Kopf und
 |    Schritt-Leiste tasten, um zu hoeren, was der neue Schritt will. Er springt
 |    deshalb auf die Ueberschrift der Karte.
 |
 | 2. Der sanfte Einlauf. Die Karte laeuft einmal kurz von rechts herein, damit
 |    der Wechsel als Bewegung lesbar ist und nicht als Blinken. Die Animation
 |    steht in resources/css/components/step-rail.css und respektiert
 |    prefers-reduced-motion -- gefragt wird hier zusaetzlich, weil ohne die
 |    Klasse gar nichts passiert und das die ruhigere Variante ist.
 |
 | Mehr tut dieses Modul nicht: Validierung, Speichern und der Weg zum
 | naechsten Schritt gehoeren dem Server (App\Livewire\Portal\Onboarding\Wizard).
 */

export function setUpWizard() {
    const step = document.querySelector('[data-wizard-step]');

    if (step === null) {
        return;
    }

    if (! prefersReducedMotion()) {
        step.classList.add('wizard-step--entering');
    }

    focusHeading(step);
}

/**
 * Hat der Nutzer eine ruhige Darstellung verlangt?
 *
 * @returns {boolean}
 */
function prefersReducedMotion() {
    return window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true;
}

/**
 * Setzt den Fokus auf die Ueberschrift des Schritts.
 *
 * preventScroll, weil der Sprung sonst die Schritt-Leiste aus dem Bild
 * schieben kann -- sie ist nach dem Wechsel die erste Antwort auf "wo bin
 * ich?".
 *
 * @param {HTMLElement} step
 */
function focusHeading(step) {
    const heading = step.querySelector('h1');

    if (heading instanceof HTMLElement) {
        heading.focus({ preventScroll: true });
    }
}
