/*
 | Ein Zaehler, der herunterlaeuft (LP-ONBOARD-005).
 |
 | Gebraucht ueberall, wo der Server eine Wartezeit in Sekunden mitgibt und der
 | Nutzer sie ablaufen sehen soll -- zuerst in der Rufnummernbestaetigung
 | ("Erneut senden in 0:59"). Ein wire:poll waere dafuer eine Anfrage je
 | Sekunde, nur um eine Ziffer kleiner zu machen.
 |
 | Als Alpine-Komponente und nicht als eigenes Skript: Die Zeile, die den
 | Zaehler zeigt, soll ihn auch anzeigen duerfen, ohne dass hier jemand ihre
 | Kennung kennt. Verwendung im Markup:
 |
 |   <p x-data="countdown(59)" x-text="clock"></p>
 |
 | `left` sind die Sekunden, `clock` ist "0:59". Was bei null passiert,
 | entscheidet das Markup ueber x-show -- dieser Zaehler schaltet nichts frei.
 | Das ist Absicht: Ob wieder gesendet werden darf, weiss der Server, und ein
 | Zaehler im Browser ist nicht die Stelle, an der ein Limit faellt.
 |
 | Registriert wird auf alpine:init und damit auf der obersten Ebene des
 | Moduls, nicht in DOMContentLoaded: Alpine startet mit Livewires Skript am
 | Ende des Dokuments und ist bis dahin schon durch -- eine Registrierung in
 | DOMContentLoaded kaeme zu spaet.
 */

export function setUpCountdown() {
    document.addEventListener('alpine:init', () => {
        window.Alpine?.data('countdown', (seconds = 0) => ({
            left: Math.max(0, Number(seconds) || 0),

            /**
             * Minuten und Sekunden, zweistellig: "0:59", "1:05".
             */
            get clock() {
                return Math.floor(this.left / 60) + ':' + String(this.left % 60).padStart(2, '0');
            },

            init() {
                if (this.left === 0) {
                    return;
                }

                const tick = setInterval(() => {
                    this.left--;

                    if (this.left <= 0) {
                        clearInterval(tick);
                    }
                }, 1000);

                // Aufraeumen, wenn Livewire das Markup austauscht -- sonst
                // laeuft je Schrittwechsel ein Intervall weiter.
                this.$cleanup?.(() => clearInterval(tick));
            },
        }));
    });
}
