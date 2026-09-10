/*
 | Verhalten der oeffentlichen Seiten.
 |
 | Bewusst ohne Inline-Handler im Markup: Ein onclick im HTML laesst sich weder
 | pruefen noch mit einer strengen Content-Security-Policy betreiben. Die
 | Elemente tragen stattdessen data-Attribute, dieses Skript haengt sich daran.
 */

document.addEventListener('DOMContentLoaded', () => {
    setUpMobileMenu();
});

/**
 * Klappt das Menue auf schmalen Bildschirmen auf und zu. Der Knopf verweist
 * ueber data-menu-toggle auf die Kennung des Menues und meldet seinen Zustand
 * ueber aria-expanded, damit Screenreader ihn mitbekommen.
 */
function setUpMobileMenu() {
    document.querySelectorAll('[data-menu-toggle]').forEach((button) => {
        const menu = document.getElementById(button.dataset.menuToggle);

        if (menu === null) {
            return;
        }

        button.addEventListener('click', () => {
            const isHidden = menu.classList.toggle('hidden');

            button.setAttribute('aria-expanded', String(! isHidden));
        });
    });
}
