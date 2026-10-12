/*
 | Antworten der Onboarding-Sperre abfangen (LP-ONBOARD-003).
 |
 | EnsurePhoneVerified beantwortet eine Livewire- oder JSON-Anfrage nicht mit
 | einem HTML-Redirect, sondern mit 403 und
 | {reason: 'phone_unverified', redirect: url}. Der Grund: Ein 302 auf eine
 | fetch()-Anfrage wird stillschweigend verfolgt, der Browser bekaeme das HTML
 | des Verifizierungs-Screens als Livewire-Antwort und wuerde es wegwerfen --
 | der Nutzer sitzt weiter auf der gesperrten Seite, nur ohne Reaktion auf
 | seinen Klick.
 |
 | Dieses Modul uebernimmt deshalb die Umleitung: Es horcht an den
 | Livewire-Fehlschlaegen und zusaetzlich an window.fetch, weil das Portal
 | daneben eigene fetch()-Aufrufe hat (Aufladung, Zahlungsmittel,
 | Rufnummernbestaetigung).
 |
 | Kein Neuladen in einer Schleife: Umgeleitet wird nur, wenn die Antwort den
 | Grund ausdruecklich nennt, und nur einmal -- danach ist die Seite ohnehin
 | eine andere.
 */

/** Verhindert, dass mehrere parallele Anfragen dieselbe Umleitung ausloesen. */
let redirecting = false;

const REASON = 'phone_unverified';

export function setUpAuthGuard() {
    guardLivewire();
    guardFetch();
}

/**
 * Leitet zum Verifizierungs-Screen um.
 *
 * @param {string} url
 */
function redirectTo(url) {
    if (redirecting || typeof url !== 'string' || url === '') {
        return;
    }

    redirecting = true;
    window.location.assign(url);
}

/**
 * Liest den Grund aus einer Antwort. Der Rumpf kann als Text oder als bereits
 * geparstes Objekt kommen -- Livewire uebergibt den Text, fetch() gibt die
 * Antwort selbst.
 *
 * @param {unknown} body
 * @returns {string|null} Die Zieladresse, oder null wenn es nicht die Sperre war
 */
function targetFrom(body) {
    const payload = typeof body === 'string' ? parse(body) : body;

    if (payload === null || typeof payload !== 'object') {
        return null;
    }

    return payload.reason === REASON && typeof payload.redirect === 'string'
        ? payload.redirect
        : null;
}

/**
 * @param {string} text
 * @returns {object|null}
 */
function parse(text) {
    try {
        return JSON.parse(text);
    } catch {
        // Ein 403 aus anderer Quelle traegt oft eine Fehlerseite im Rumpf.
        return null;
    }
}

/**
 * Fehlgeschlagene Livewire-Anfragen.
 *
 * preventDefault() haelt Livewires eigene Fehlerbehandlung an: Ohne das
 * blitzte der 403-Bildschirm im Fehler-Overlay auf, bevor die Umleitung
 * greift.
 */
function guardLivewire() {
    document.addEventListener('livewire:init', () => {
        window.Livewire.hook('request', ({ fail }) => {
            fail(({ status, content, preventDefault }) => {
                if (status !== 403) {
                    return;
                }

                const target = targetFrom(content);

                if (target === null) {
                    return;
                }

                preventDefault?.();
                redirectTo(target);
            });
        });
    });
}

/**
 * Eigene fetch()-Aufrufe des Portals.
 *
 * Die Antwort wird geklont, bevor sie gelesen wird -- der Rumpf laesst sich
 * nur einmal auslesen, und der aufrufende Code braucht ihn noch.
 */
function guardFetch() {
    const original = window.fetch.bind(window);

    window.fetch = async (...args) => {
        const response = await original(...args);

        if (response.status !== 403) {
            return response;
        }

        try {
            const target = targetFrom(await response.clone().json());

            if (target !== null) {
                redirectTo(target);
            }
        } catch {
            // Kein JSON-Rumpf: dann war es nicht die Sperre.
        }

        return response;
    };
}
