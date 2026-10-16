<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Gefuehrte Touren (LP-ONBOARD-013)
|--------------------------------------------------------------------------
|
| Zwei kurze Touren, die beim ersten Besuch je drei bis vier Elemente mit
| einem Spotlight erklaeren. Die Texte stehen hier und nicht im Skript:
| resources/js/modules/tour.js liest Titel und Text aus den
| data-tour-Attributen des Markups, und das Markup uebersetzt mit __().
|
| Jeder Text sagt, was der Kaeufer davon hat, nicht was das Element ist. "Hier
| grenzt du Anfragen ein" ist eine Hilfe -- "Filterbereich" ist eine
| Beschriftung, und die steht schon am Knopf.
|
| Du-Form wie im ganzen Portal, ein Satz je Schritt. Wer vier Saetze braucht,
| hat eine Dokumentationsseite und keine Tour.
*/

return [
    /*
    | Die Knoepfe der Sprechblase. Sie heissen in beiden Touren gleich.
    |
    | "progress" setzt driver.js selbst ein, die Platzhalter {{current}} und
    | {{total}} muessen deshalb genau so stehen bleiben.
    */
    'controls' => [
        'next' => 'Weiter',
        'previous' => 'Zurück',
        'done' => 'Fertig',
        'close' => 'Überspringen',
        'progress' => '{{current}} von {{total}}',
    ],

    /* Der Eintrag im Kontomenü, der die Tour der aktuellen Seite erneut startet. */
    'restart' => 'Tour erneut starten',

    'marketplace' => [
        'balance' => [
            'title' => 'Dein Guthaben',
            'text' => 'Hier siehst du, wofür dein Guthaben reicht.',
        ],
        'lead' => [
            'title' => 'Die Anfragen',
            'text' => 'Jede Karte ist eine echte Anfrage – Name und Ort siehst du sofort.',
        ],
        'filters' => [
            'title' => 'Passend machen',
            'text' => 'Hier grenzt du Anfragen ein.',
        ],
        'purchase' => [
            'title' => 'Lead kaufen',
            'text' => 'Kaufen reserviert den Betrag nur – abgebucht wird erst nach dem angenommenen Anruf.',
        ],
    ],

    'lead_detail' => [
        'contact' => [
            'title' => 'Die Kontaktdaten',
            'text' => 'Die Nummer bleibt verdeckt, du rufst direkt über widileads an.',
        ],
        'call' => [
            'title' => 'Jetzt anrufen',
            'text' => 'Ein Tipp hier verbindet dich – erst klingelt dein Telefon, dann der Interessent. Er sieht deine bestätigte Rufnummer.',
        ],
        'deadline' => [
            'title' => 'Deine Frist',
            'text' => 'So lange hast du Zeit für deine Anrufversuche.',
        ],
        'history' => [
            'title' => 'Anrufhistorie',
            'text' => 'Hier siehst du jeden Versuch – das ist auch dein Nachweis.',
        ],
    ],
];
