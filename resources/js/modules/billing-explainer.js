/*
 | Der 3-Schritte-Player "So wird abgerechnet" (LP-ONBOARD-011).
 |
 | Das Auf und Ab zwischen den drei Schritten der Abrechnungs-Erklaerung: Punkte,
 | "Weiter", und -- nur wenn der Nutzer Bewegung nicht abbestellt hat -- eine
 | automatische Vorfuehrung, die beim ersten Eingriff endet und nicht wieder
 | anspringt. Wer selbst blaettert, hat verstanden, dass er blaettern darf.
 |
 | Als Alpine-Komponente und nicht als Livewire-Zustand: Ein Schrittwechsel ist
 | eine Ansicht. Auf dem Server waere jedes "Weiter" eine Anfrage und die
 | Vorfuehrung eine Anfrage je vier Sekunden.
 |
 | Verwendung im Markup (resources/views/livewire/portal/onboarding/
 | billing-explainer.blade.php):
 |
 |   <div x-data="billingExplainer({ count: 3, animation: { from: 5000, to: 3110, currency: '€' } })">
 |
 | `step` ist der Schritt (0-basiert), `amount` der Text der Guthabenzahl.
 | Jeder Schritt steht vollstaendig im HTML und wird nur ein- und ausgeblendet:
 | Dieses Skript schreibt keinen Satz und keinen Betrag.
 |
 | Registriert wird auf alpine:init und damit auf der obersten Ebene des
 | Moduls, nicht in DOMContentLoaded -- Alpine startet mit Livewires Skript am
 | Ende des Dokuments und ist bis dahin schon durch.
 */

/** Wie lange ein Schritt in der automatischen Vorfuehrung steht. */
const AUTOPLAY_MS = 4500;

/** Dauer der herunterlaufenden Guthabenzahl und ihre Schrittweite. */
const COUNT_MS = 700;
const COUNT_STEPS = 24;

export function setUpBillingExplainer() {
    document.addEventListener('alpine:init', () => {
        window.Alpine?.data('billingExplainer', (options = {}) => ({
            /** Der sichtbare Schritt, zaehlt ab 0. */
            step: 0,

            /** Wie viele Schritte es gibt -- der Server zaehlt sie. */
            count: Math.max(1, Number(options.count) || 1),

            /** Von -- bis in Cent, plus das Waehrungszeichen. */
            animation: options.animation ?? null,

            /** Der aktuelle Stand der Guthabenzahl in Cent. */
            cents: Number(options.animation?.from) || 0,

            /** Laeuft die automatische Vorfuehrung noch? */
            playing: false,

            /** Mag der Nutzer keine Bewegung? */
            reduced: false,

            timer: null,
            counting: null,

            init() {
                const query = window.matchMedia?.('(prefers-reduced-motion: reduce)');

                this.reduced = query?.matches === true;

                // Ohne Bewegung keine Vorfuehrung: Wer sie abbestellt hat,
                // blaettert selbst -- jeder Schritt ist auch im Stillstand
                // vollstaendig lesbar. Dasselbe gilt, wo der Aufrufer sie
                // abschaltet (Player im geschlossenen Dialog).
                if (! this.reduced && options.autoplay !== false) {
                    this.play();
                }

                this.applyAmount();

                this.$cleanup?.(() => this.stop());
            },

            /**
             * Der Betrag der Guthabenzahl, in der Schreibweise des Portals:
             * "50,00 €". Formatiert wird hier und nicht auf dem Server, weil
             * jeder Zwischenstand sonst als eigene Zeichenkette im Markup
             * stehen muesste.
             */
            get amount() {
                const symbol = this.animation?.currency ?? '';

                return (this.cents / 100)
                    .toFixed(2)
                    .replace('.', ',')
                    .replace(/\B(?=(\d{3})+(?!\d))/g, '.') + (symbol === '' ? '' : ' ' + symbol);
            },

            /**
             * Springt auf einen Schritt. Jeder Eingriff beendet die
             * Vorfuehrung -- sie wieder anzuwerfen, waere ein Player, der dem
             * Nutzer die Anzeige aus der Hand nimmt.
             */
            go(index) {
                this.stop();
                this.show(index);
            },

            /** "Weiter" -- am Ende wieder von vorn. */
            next() {
                this.go((this.step + 1) % this.count);
            },

            /** Steht der Player auf dem letzten Schritt? */
            get isLast() {
                return this.step === this.count - 1;
            },

            show(index) {
                this.step = ((Number(index) || 0) + this.count) % this.count;
                this.applyAmount();
            },

            play() {
                this.playing = true;

                this.timer = setInterval(() => {
                    this.show((this.step + 1) % this.count);
                }, AUTOPLAY_MS);
            },

            stop() {
                this.playing = false;

                clearInterval(this.timer);
                clearInterval(this.counting);

                this.timer = null;
                this.counting = null;
            },

            /**
             * Die Guthabenzahl zum Schritt: Im ersten steht der Anfangsstand,
             * im zweiten laeuft sie auf den Stand nach der Abbuchung herunter,
             * im dritten steht sie wieder oben -- der Lead war kostenlos.
             */
            applyAmount() {
                if (this.animation === null) {
                    return;
                }

                clearInterval(this.counting);
                this.counting = null;

                const from = Number(this.animation.from) || 0;
                const to = Number(this.animation.to) || 0;

                if (this.step !== 1) {
                    this.cents = from;

                    return;
                }

                if (this.reduced) {
                    this.cents = to;

                    return;
                }

                let tick = 0;

                this.cents = from;

                this.counting = setInterval(() => {
                    tick++;

                    this.cents = tick >= COUNT_STEPS
                        ? to
                        : Math.round(from + ((to - from) * tick) / COUNT_STEPS);

                    if (tick >= COUNT_STEPS) {
                        clearInterval(this.counting);
                        this.counting = null;
                    }
                }, COUNT_MS / COUNT_STEPS);
            },
        }));
    });
}
