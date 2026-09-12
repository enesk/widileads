/*
 | Verhalten der oeffentlichen Seiten.
 |
 | Bewusst ohne Inline-Handler im Markup: Ein onclick im HTML laesst sich weder
 | pruefen noch mit einer strengen Content-Security-Policy betreiben. Die
 | Elemente tragen stattdessen data-Attribute, dieses Skript haengt sich daran.
 */

import { setUpPaymentMethod } from './modules/payment-method.js';
import { setUpPhoneVerify } from './modules/phone-verify.js';
import { setUpToasts } from './modules/toasts.js';
import { setUpTopUp } from './modules/topup.js';

document.addEventListener('DOMContentLoaded', () => {
    setUpMobileMenu();
    setUpDropdowns();
    setUpMenuEscape();
    setUpPasswordToggles();
    setUpScrollTargets();
    setUpTopUp();
    setUpPhoneVerify();
    setUpPaymentMethod();
    setUpToasts();
    setUpDialogEscape();
    setUpTabStrips();
});

/**
 * Schliesst ein offenes Menue und zieht den Zustand an allen Schaltern nach,
 * die darauf verweisen. Ein Menue kann mehrere Schalter haben -- die Schublade
 * wird vom Knopf im Kopf geoeffnet und von der Flaeche dahinter geschlossen.
 *
 * @param {HTMLElement} menu
 * @param {string} attribute
 */
function closeMenu(menu, attribute) {
    if (menu.classList.contains('hidden')) {
        return;
    }

    menu.classList.add('hidden');

    document.querySelectorAll(`[${attribute}="${menu.id}"]`).forEach((button) => {
        button.setAttribute('aria-expanded', 'false');
    });
}

/**
 * Schliesst offene Menues mit Escape. Die abgedunkelte Flaeche hinter der
 * Schublade nimmt nur Klicks an -- ohne diesen Weg kommt man mit der Tastatur
 * nicht wieder heraus.
 */
function setUpMenuEscape() {
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        closeAll('data-menu-toggle');
        closeAll('data-dropdown-toggle');

        const trigger = document.activeElement?.closest?.('[data-dropdown-toggle]');

        if (trigger instanceof HTMLElement) {
            trigger.focus();
        }
    });
}

/**
 * Schliesst alle Menues, auf die Schalter mit diesem Attribut verweisen.
 *
 * @param {string} attribute
 */
function closeAll(attribute) {
    document.querySelectorAll(`[${attribute}]`).forEach((button) => {
        const menu = document.getElementById(button.getAttribute(attribute));

        if (menu !== null) {
            closeMenu(menu, attribute);
        }
    });
}

/**
 * Klappt ein Menue am Knopf auf und zu -- Workspace-Wechsler und Kontomenue im
 * Kopf des Arbeitsbereichs. Der Schalter verweist ueber data-dropdown-toggle
 * auf die Kennung des Menues.
 *
 * Anders als die Schublade liegt ein solches Menue ueber der Seite: Es schliesst
 * beim Klick daneben, und es schliesst die uebrigen offenen Menues, damit nicht
 * zwei gleichzeitig aufklappen. Positioniert wird im Markup, hier steht nur das
 * Auf und Zu.
 */
function setUpDropdowns() {
    document.querySelectorAll('[data-dropdown-toggle]').forEach((button) => {
        const menu = document.getElementById(button.dataset.dropdownToggle);

        if (menu === null) {
            return;
        }

        button.addEventListener('click', (event) => {
            event.stopPropagation();

            const wasHidden = menu.classList.contains('hidden');

            closeAll('data-dropdown-toggle');

            if (wasHidden) {
                menu.classList.remove('hidden');
                button.setAttribute('aria-expanded', 'true');
            }
        });
    });

    document.addEventListener('click', (event) => {
        document.querySelectorAll('[data-dropdown-toggle]').forEach((button) => {
            const menu = document.getElementById(button.dataset.dropdownToggle);

            if (menu !== null && ! menu.contains(event.target)) {
                closeMenu(menu, 'data-dropdown-toggle');
            }
        });
    });
}

/**
 * Klappt das Menue auf schmalen Bildschirmen auf und zu. Der Schalter verweist
 * ueber data-menu-toggle auf die Kennung des Menues und meldet seinen Zustand
 * ueber aria-expanded, damit Screenreader ihn mitbekommen.
 *
 * Ein Menue kann mehrere Schalter haben: die Schublade des Arbeitsbereichs
 * wird vom Knopf im Kopf geoeffnet und von der abgedunkelten Flaeche dahinter
 * wieder geschlossen. Nach dem Umschalten wird der Zustand deshalb an allen
 * Schaltern desselben Menues nachgezogen, sonst behauptet der Knopf im Kopf
 * weiter, die Schublade sei offen.
 */
function setUpMobileMenu() {
    document.querySelectorAll('[data-menu-toggle]').forEach((button) => {
        const id = button.dataset.menuToggle;
        const menu = document.getElementById(id);

        if (menu === null) {
            return;
        }

        button.addEventListener('click', () => {
            const isHidden = menu.classList.toggle('hidden');

            document.querySelectorAll(`[data-menu-toggle="${id}"]`).forEach((sibling) => {
                sibling.setAttribute('aria-expanded', String(! isHidden));
            });
        });
    });
}

/**
 * Zeigt ein Passwort im Klartext und wieder verdeckt. Der Knopf verweist ueber
 * data-password-toggle auf die Kennung des Feldes.
 */
function setUpPasswordToggles() {
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        const field = document.getElementById(button.dataset.passwordToggle);

        if (field === null) {
            return;
        }

        button.addEventListener('click', () => {
            field.type = field.type === 'password' ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(field.type === 'text'));
        });
    });
}

/**
 * Springt zu einem Element auf der Seite -- weich und mittig, damit die Karte
 * nicht am oberen Rand unter dem festen Kopf klebt. Der Knopf nennt die Kennung
 * des Ziels in data-scroll-to und bleibt daneben ein gewoehnlicher Anker, der
 * ohne dieses Skript ebenfalls ans Ziel kommt.
 *
 * Gebunden wird am Dokument statt an jedem Knopf: Die Liste wird von Livewire
 * neu gezeichnet, danach waere ein Verweis auf das alte Element ins Leere
 * gelaufen.
 */
function setUpScrollTargets() {
    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-scroll-to]');

        if (trigger === null) {
            return;
        }

        const target = document.getElementById(trigger.dataset.scrollTo);

        if (target === null) {
            return;
        }

        event.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
}

/**
 * Escape schliesst ein offenes Blatt.
 *
 * Das Blatt selbst gehoert Livewire: Es steht nur im HTML, solange der Server
 * einen offenen Lead kennt, und geht ueber wire:click wieder zu. Nur die Taste
 * hat dort keinen Platz -- deshalb diese Zeile. Gebunden wird am Dokument,
 * weil das Blatt erst nach einer Antwort entsteht.
 */
function setUpDialogEscape() {
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        const dialog = document.querySelector('[data-dialog]');

        if (dialog === null) {
            return;
        }

        // Derselbe Weg wie der Klick daneben: der Server entscheidet.
        dialog.querySelector('[wire\\:click="closeLead"]')?.click();
    });
}

/**
 * Schiebt den aktiven Reiter in die Mitte seiner scrollbaren Leiste.
 *
 * Auf dem Telefon passen vier Reiter nicht nebeneinander. Wer den letzten
 * waehlt, soll ihn danach sehen und nicht am rechten Rand suchen. Laeuft auch
 * nach jeder Livewire-Antwort, weil die Leiste dann neu gezeichnet ist.
 */
function setUpTabStrips() {
    const center = () => {
        document.querySelectorAll('[data-tabstrip]').forEach((strip) => {
            const active = strip.querySelector('[aria-selected="true"]');

            if (active === null) {
                return;
            }

            const ziel = active.offsetLeft - (strip.clientWidth - active.offsetWidth) / 2;

            strip.scrollTo({ left: Math.max(0, ziel), behavior: 'smooth' });
        });
    };

    center();

    document.addEventListener('livewire:navigated', center);
    window.addEventListener('livewire:update', center);
    document.addEventListener('livewire:initialized', () => {
        window.Livewire?.hook?.('morphed', center);
    });
}
