/*
 | Zahlungsmittel hinterlegen (LP-POSTPAID-010).
 |
 | Vier Schritte: Art waehlen, Daten bei Stripe eingeben, bei Lastschrift das
 | Mandat bestaetigen, fertig.
 |
 | IBAN und Kartennummer beruehren diese Anwendung nie. Sie entstehen in Stripe
 | Elements -- einem iframe von Stripe -- und gehen von dort direkt an Stripe;
 | zurueck kommt nur die Kennung des SetupIntent. Genau deshalb laeuft dieser
 | Schritt im Browser und nicht ueber Livewire.
 |
 | Aufgerufen werden ausschliesslich zwei eigene Adressen (SetupIntent holen,
 | danach bestaetigen) und zwei Stripe-Aufrufe (Elements und confirmSetup).
 | Alles Weitere -- speichern, Standard setzen, Mandat belegen -- entscheidet
 | der Server in App\Services\Payments\PaymentMethodService.
 |
 | Saemtliche Adressen, Texte und Kennungen kommen aus data-Attributen des
 | Rahmens; im Skript steht kein Text und keine Adresse.
 */

const SEPA = 'sepa_debit';
const LAST_STEP = 4;

export function setUpPaymentMethod() {
    const root = document.querySelector('[data-payment-method-form]');

    if (root === null) {
        return;
    }

    const state = {
        root,
        step: 1,
        type: null,
        setupIntentId: null,
        stripe: null,
        elements: null,
    };

    document.querySelectorAll('[data-pm-open]').forEach((button) => {
        button.addEventListener('click', () => open(state));
    });

    root.querySelectorAll('[data-pm-close]').forEach((button) => {
        button.addEventListener('click', () => close(state));
    });

    root.querySelectorAll('[data-pm-back]').forEach((button) => {
        button.addEventListener('click', () => {
            // Zurueck aus dem Mandat geht auf die Eingabe, zurueck aus der
            // Eingabe auf die Wahl der Art.
            show(state, Number(button.dataset.pmBack) - 1);
        });
    });

    root.querySelectorAll('[data-pm-next]').forEach((button) => {
        button.addEventListener('click', () => {
            advance(state, Number(button.dataset.pmNext), button);
        });
    });
}

/**
 * Oeffnet den Stepper bei Schritt 1 und setzt eine fruehere Eingabe zurueck.
 *
 * @param {object} state
 */
function open(state) {
    state.type = null;
    state.setupIntentId = null;
    state.elements = null;

    state.root.querySelectorAll('input[name=pm_type]').forEach((radio) => {
        radio.checked = false;
    });

    const mandate = state.root.querySelector('[data-pm-mandate]');

    if (mandate !== null) {
        mandate.checked = false;
    }

    state.root.classList.remove('hidden');
    error(state, null);
    show(state, 1);
    state.root.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

/**
 * @param {object} state
 */
function close(state) {
    state.root.classList.add('hidden');
    error(state, null);
}

/**
 * Zeigt einen Schritt und zieht die Marken darueber nach.
 *
 * @param {object} state
 * @param {number} step
 */
function show(state, step) {
    state.step = step;

    state.root.querySelectorAll('[data-pm-step]').forEach((panel) => {
        panel.hidden = Number(panel.dataset.pmStep) !== step;
    });

    state.root.querySelectorAll('[data-pm-marker]').forEach((marker) => {
        const index = Number(marker.dataset.pmMarker);

        marker.classList.toggle('pm-step-current', index === step);
        marker.classList.toggle('pm-step-done', index < step);
    });
}

/**
 * Der Schritt nach vorn. Welcher es ist, haengt am aktuellen Schritt und an
 * der gewaehlten Art: Eine Karte kennt kein Mandat und ueberspringt Schritt 3.
 *
 * @param {object} state
 * @param {number} from
 * @param {HTMLElement} button
 */
async function advance(state, from, button) {
    error(state, null);
    button.disabled = true;

    try {
        if (from === 1) {
            await startSetup(state);
        } else if (from === 2) {
            await confirmWithStripe(state);
        } else if (from === 3) {
            await save(state);
        }
    } catch (exception) {
        error(state, exception instanceof Error ? exception.message : String(exception));
    } finally {
        button.disabled = false;
    }
}

/**
 * Schritt 1 -> 2: SetupIntent holen und Stripe Elements einhaengen.
 *
 * @param {object} state
 */
async function startSetup(state) {
    const chosen = state.root.querySelector('input[name=pm_type]:checked');

    if (chosen === null) {
        return;
    }

    state.type = chosen.value;

    const setup = await post(state, state.root.dataset.setupUrl, {
        type: state.type,
        tenant: state.root.dataset.tenant,
    });

    if (typeof window.Stripe !== 'function') {
        throw new Error(state.root.dataset.unavailable);
    }

    state.setupIntentId = setup.setup_intent_id;
    state.stripe = window.Stripe(setup.publishable_key);
    state.elements = state.stripe.elements({
        clientSecret: setup.client_secret,
        locale: state.root.dataset.locale ?? 'auto',
    });

    const target = state.root.querySelector('#pm-element');
    target.replaceChildren();
    state.elements.create('payment').mount(target);

    show(state, 2);
}

/**
 * Schritt 2 weiter: Die Eingabe bei Stripe abschliessen.
 *
 * `redirect: 'if_required'` haelt den Kaeufer auf dieser Seite, solange Stripe
 * keine Weiterleitung braucht. Bei einer Karte mit 3DS-Abfrage braucht es eine,
 * deshalb steht die Rueckkehradresse trotzdem dabei.
 *
 * @param {object} state
 */
async function confirmWithStripe(state) {
    const { error: failure } = await state.stripe.confirmSetup({
        elements: state.elements,
        confirmParams: { return_url: state.root.dataset.returnUrl },
        redirect: 'if_required',
    });

    if (failure) {
        throw new Error(failure.message);
    }

    // Bei Lastschrift folgt das Mandat, bei Karte ist es geschafft.
    if (state.type === SEPA) {
        show(state, 3);

        return;
    }

    await save(state);
}

/**
 * Letzter Schritt: Das bei Stripe entstandene Mittel uebernehmen lassen.
 *
 * Die Mandatseinwilligung reist als Feld mit, wird aber serverseitig noch
 * einmal geprueft -- ein Haekchen im Browser ist keine Zusicherung.
 *
 * @param {object} state
 */
async function save(state) {
    const mandate = state.root.querySelector('[data-pm-mandate]');
    const accepted = state.type === SEPA ? mandate?.checked === true : true;

    if (! accepted) {
        throw new Error(state.root.dataset.mandateRequired);
    }

    const result = await post(state, state.root.dataset.confirmUrl, {
        setup_intent_id: state.setupIntentId,
        type: state.type,
        tenant: state.root.dataset.tenant,
        mandate_accepted: accepted ? 1 : 0,
    });

    const label = result.payment_method?.label ?? '';
    const done = state.root.querySelector('[data-pm-done-text]');

    if (done !== null) {
        done.textContent = (state.root.dataset.doneTemplate ?? '').replace(':method', label);
    }

    show(state, LAST_STEP);

    // Die Liste daneben gehoert Livewire und weiss von alldem nichts.
    window.Livewire?.dispatch('payment-method-added');
}

/**
 * Ein Aufruf an die eigenen Adressen. Eine Fehlermeldung des Servers ist
 * gedacht, gelesen zu werden -- sie wird durchgereicht statt durch einen
 * eigenen Satz ersetzt.
 *
 * @param {object} state
 * @param {string} url
 * @param {object} payload
 */
async function post(state, url, payload) {
    const token = document.querySelector('meta[name=csrf-token]')?.content ?? '';

    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': token,
        },
        body: JSON.stringify(payload),
    });

    const body = await response.json().catch(() => ({}));

    if (! response.ok) {
        throw new Error(
            body.message
                ?? Object.values(body.errors ?? {})[0]?.[0]
                ?? state.root.dataset.unavailable,
        );
    }

    return body;
}

/**
 * Zeigt eine Fehlermeldung oder raeumt sie weg.
 *
 * @param {object} state
 * @param {string|null} message
 */
function error(state, message) {
    const target = state.root.querySelector('[data-pm-error]');

    if (target === null) {
        return;
    }

    target.textContent = message ?? '';
    target.classList.toggle('hidden', message === null || message === '');
}
