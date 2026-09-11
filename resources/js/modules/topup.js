/*
 | Livesumme der Aufladeseite (Portal Phase 1).
 |
 | Rechnet ausschliesslich fuer die Anzeige. Geprueft und gebucht wird
 | serverseitig -- was hier steht, ist eine Vorschau derselben Zahlen.
 |
 | Alle Werte kommen aus data-Attributen des Formulars, damit im Skript weder
 | ein Betrag noch ein Steuersatz steht: data-balance (aktuelles Guthaben in
 | Euro), data-vat (Steuersatz in Prozent), data-pay-label (Beschriftung des
 | Zahlknopfs mit :amount als Platzhalter).
 |
 | Der freie Betrag und die Pakete tragen denselben Feldnamen. Immer genau eines
 | der beiden Felder ist aktiv, das andere ist `disabled` -- so geht auch bei
 | offenem Eingabefeld nur ein Betrag an den Server.
 */

export function setUpTopUp() {
    const form = document.getElementById('topup');

    if (form === null) {
        return;
    }

    const custom = document.getElementById('custom');
    const customInput = document.getElementById('amount');
    const packages = Array.from(form.querySelectorAll('input[type=radio][name=amount_euro]'));
    const balance = Number(form.dataset.balance ?? 0);
    const vatPercent = Number(form.dataset.vat ?? 0);
    const payLabel = form.dataset.payLabel ?? ':amount';

    const format = (amount) => amount.toLocaleString('de-DE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }) + ' €';

    const write = (selector, text) => {
        const target = form.querySelector(selector);

        if (target !== null) {
            target.textContent = text;
        }
    };

    const show = (amount) => {
        const vat = vatPercent > 0 ? amount - amount / (1 + vatPercent / 100) : 0;

        write('[data-sum]', format(amount));
        write('[data-vat-amount]', format(vat));
        write('[data-total]', format(amount));
        write('[data-after]', format(balance + amount));
        write('[data-pay]', payLabel.replace(':amount', format(amount)));
    };

    const selected = () => {
        if (customInput !== null && ! customInput.disabled) {
            return Number(customInput.value) || 0;
        }

        const checked = packages.find((radio) => radio.checked);

        return checked === undefined ? 0 : Number(checked.value);
    };

    packages.forEach((radio) => {
        radio.addEventListener('change', () => {
            // Ein Paket gewaehlt: Der freie Betrag gilt nicht mehr und geht
            // deshalb auch nicht mit ans Formular.
            if (customInput !== null) {
                customInput.disabled = true;
            }

            show(selected());
        });
    });

    if (customInput !== null) {
        customInput.addEventListener('input', () => show(selected()));
    }

    form.querySelectorAll('[data-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const target = document.getElementById(button.dataset.toggle);

            if (target === null) {
                return;
            }

            const opened = target.classList.toggle('hidden') === false;

            if (target === custom && customInput !== null) {
                customInput.disabled = ! opened;

                if (opened) {
                    packages.forEach((radio) => {
                        radio.checked = false;
                    });
                    customInput.focus();
                } else {
                    const first = packages[0];

                    if (first !== undefined) {
                        first.checked = true;
                    }
                }

                show(selected());
            }
        });
    });

    show(selected());
}
