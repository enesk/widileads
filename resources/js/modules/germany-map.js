/*
 | Pin, Umkreis und Schieberegler auf der Deutschland-Karte (LP-ONBOARD-009).
 |
 | Warum ueberhaupt Skript: Der Kreis muss dem Regler folgen, waehrend der
 | Finger noch darauf liegt. Eine Serverrunde je Zwischenwert waere ein
 | Nachziehen im Halbsekundentakt -- und genau das, was an einem Regler als
 | "haengt" empfunden wird. Die Zahl unter der Karte kommt weiterhin vom Server
 | (wire:model.live.debounce.300ms): Sie haengt an den Leads der letzten vier
 | Wochen, die kennt der Browser nicht.
 |
 | Die Aufteilung ist damit fest:
 |   Browser -- wo der Pin steht, wie gross der Kreis ist, wie weit die Schiene
 |              gefuellt ist, welche Zahl am Regler steht.
 |   Server  -- Ortsname, Anfragezahl, Pruefung und Speichern.
 |
 | Die Projektion ist dieselbe wie in App\Support\Geo\SimpleProjection; ihre
 | vier Konstanten kommen als data-Attribute herein, damit sie nicht an zwei
 | Stellen gepflegt werden. Gerechnet wird hier nur fuer den Radius -- den
 | Mittelpunkt kennt der Browser erst, wenn der Server die Postleitzahl
 | nachgeschlagen hat, und bis dahin bleibt der alte Pin stehen.
 |
 | Gebunden wird am Dokument und nach jeder Livewire-Antwort neu: Die Karte
 | steht in einer Livewire-Komponente und wird bei jedem Wechsel der
 | Auswahlkarte neu gezeichnet.
 */

export function setUpGermanyMap() {
    const sync = () => {
        document.querySelectorAll('[data-radius-slider]').forEach((slider) => {
            updateFromSlider(slider);

            if (slider.dataset.bound === '1') {
                return;
            }

            slider.dataset.bound = '1';
            slider.addEventListener('input', () => updateFromSlider(slider));
        });
    };

    sync();

    document.addEventListener('livewire:navigated', sync);
    document.addEventListener('livewire:initialized', () => {
        window.Livewire?.hook?.('morphed', sync);
    });
}

/**
 * Zieht alles nach, was am Reglerwert haengt: die Zahl daneben, die gefuellte
 * Schiene und der Kreis auf der Karte.
 *
 * @param {HTMLInputElement} slider
 */
function updateFromSlider(slider) {
    const value = Number(slider.value);
    const min = Number(slider.min || 0);
    const max = Number(slider.max || 100);

    /* Der Regler laeuft ueber die Stufen, nicht ueber Kilometer -- 10/25/50/
       100/200 liegen nicht gleich weit auseinander (siehe StepRegion). */
    const steps = readSteps(slider);
    const kilometres = steps[value] ?? value;

    const output = document.getElementById(slider.dataset.radiusSlider);

    if (output !== null) {
        output.textContent = String(kilometres);
    }

    slider.setAttribute('aria-valuetext', `${kilometres} km`);

    /* Die gefuellte Schiene. Firefox kann das selbst
       (::-moz-range-progress), WebKit braucht den Anteil als Variable --
       siehe resources/css/components/range.css. */
    const progress = max > min ? ((value - min) / (max - min)) * 100 : 0;
    slider.style.setProperty('--range-progress', `${progress}%`);

    drawRadius(kilometres);
}

/**
 * Die Stufen des Reglers, wie sie der Server mitgegeben hat.
 *
 * @param {HTMLInputElement} slider
 * @returns {number[]}
 */
function readSteps(slider) {
    try {
        const steps = JSON.parse(slider.dataset.radiusSteps ?? '[]');

        return Array.isArray(steps) ? steps.map(Number) : [];
    } catch {
        return [];
    }
}

/**
 * Setzt den Radius des Kreises auf der Karte.
 *
 * Der Mittelpunkt bleibt, wo er steht: Er kommt aus der Postleitzahl, und die
 * schlaegt der Server nach.
 *
 * @param {number} kilometres
 */
function drawRadius(kilometres) {
    document.querySelectorAll('[data-germany-map-canvas]').forEach((svg) => {
        const circle = svg.querySelector('[data-germany-map-circle]');

        if (circle === null) {
            return;
        }

        const scale = Number(svg.dataset.projectionScale);
        const kmPerDegree = Number(svg.dataset.projectionKmPerDegree);

        if (! Number.isFinite(scale) || ! Number.isFinite(kmPerDegree) || kmPerDegree === 0) {
            return;
        }

        circle.setAttribute('r', String(Math.round((kilometres * scale) / kmPerDegree * 10) / 10));
    });
}
