<?php

declare(strict_types=1);

/**
 * Rufnummernbestaetigung des Kaeufers per Twilio Verify (LP-ONBOARD-004).
 *
 * Die bestaetigte Nummer ist spaeter die Rufnummernanzeige des Click-to-Call
 * (FB-080/FB-081). Sie muss dem Nutzer deshalb nachweislich gehoeren -- alles
 * in dieser Datei dient entweder diesem Nachweis oder dem Schutz davor, dass
 * jemand ihn als Versandmaschine missbraucht.
 *
 * Die Zugangsdaten stehen NICHT hier, sondern in config/services.php
 * (`services.twilio.*`) -- eine Quelle fuer alle Twilio-Aufrufe.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Betrieb ohne Twilio
    |--------------------------------------------------------------------------
    |
    | Lokal und auf Staging gibt es keinen Verify-Service, und ein
    | Trial-Konto darf ohnehin keine Bestaetigungsanrufe aufbauen. Mit `fake`
    | wird der Versand nur protokolliert und ausschliesslich `code` akzeptiert.
    |
    | In Produktion ist der Schalter wirkungslos: Der Dienst verweigert die
    | Attrappe dort ausdruecklich (siehe PhoneVerificationService). Lieber ein
    | Fehler beim Start als ein Konto, das sich mit 000000 freischaltet.
    |
    */

    'fake' => (bool) env('PHONE_VERIFICATION_FAKE', false),

    // Der Code, den die Attrappe akzeptiert.
    'fake_code' => (string) env('PHONE_VERIFICATION_FAKE_CODE', '000000'),

    /*
    |--------------------------------------------------------------------------
    | Normalisierung
    |--------------------------------------------------------------------------
    |
    | Eingegeben wird, was der Nutzer gewohnt ist ("0172 1234567"), gespeichert
    | wird immer E.164. Gelesen wird gegen `region` als Standardvorwahl; eine
    | Nummer mit eigener Laendervorwahl darf aus jedem Land in `regions`
    | kommen. Festnetz ist erlaubt: Ein Handwerksbetrieb erreicht seine Leads
    | ueber die Werkstattnummer.
    |
    */

    'region' => (string) env('PHONE_VERIFICATION_REGION', 'DE'),

    'regions' => [
        // DACH und die EU-Nachbarn, in denen wir Kaeufer erwarten.
        'DE', 'AT', 'CH', 'NL', 'BE', 'LU', 'FR', 'IT', 'ES', 'PT',
        'DK', 'SE', 'FI', 'PL', 'CZ', 'SK', 'HU', 'SI', 'HR', 'IE',
        'GR', 'RO', 'BG', 'EE', 'LV', 'LT', 'CY', 'MT',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate-Limits
    |--------------------------------------------------------------------------
    |
    | Jede Code-Anforderung kostet Geld und klingelt bei einem echten
    | Menschen. Die Grenzen haengen deshalb an der Nummer (nicht am Konto):
    | Wer sich zehn Konten anlegt, soll eine fremde Nummer nicht zehnmal so oft
    | anrufen lassen koennen.
    |
    | Die Pruefungen haengen dagegen an der Sitzung -- sie sind der
    | Rateversuch, und ein Angreifer wuerde dafuer die Nummer wechseln, nicht
    | die Sitzung.
    |
    */

    'limits' => [

        // Code-Anforderungen je Nummer je Zeitfenster.
        'requests_per_phone' => (int) env('PHONE_VERIFICATION_REQUESTS_PER_PHONE', 3),
        'requests_window_minutes' => (int) env('PHONE_VERIFICATION_REQUESTS_WINDOW', 60),

        // Code-Pruefungen je Sitzung je Zeitfenster.
        'checks_per_session' => (int) env('PHONE_VERIFICATION_CHECKS_PER_SESSION', 5),
        'checks_window_minutes' => (int) env('PHONE_VERIFICATION_CHECKS_WINDOW', 10),

        // Frueheste Wiederholung des Versands. Der Knopf "Code erneut
        // senden" rechnet damit seinen Zaehler aus.
        'resend_after_seconds' => (int) env('PHONE_VERIFICATION_RESEND_AFTER', 60),

    ],

    /*
    |--------------------------------------------------------------------------
    | Lebensdauer eines Codes
    |--------------------------------------------------------------------------
    |
    | Nicht unsere Regel: Ein Code von Twilio Verify laeuft nach zehn Minuten
    | ab, das ist dort eingestellt und kann hier nicht verlaengert werden.
    | Dieser Wert ist die Kopie davon, und er hat genau einen Zweck -- die
    | Oberflaeche (LP-ONBOARD-005) kann damit die Meldung "abgelaufen" von
    | "falsch" unterscheiden.
    |
    | Der Dienst selbst unterscheidet das bewusst NICHT: Nach aussen heisst
    | falsch, abgelaufen und "kein offener Versuch" dasselbe, sonst waere die
    | Antwort ein Hinweis fuers Raten. Das Alter des eigenen Versuchs ist
    | dagegen kein Geheimnis -- es steht am eigenen Konto.
    |
    */

    'code_ttl_minutes' => (int) env('PHONE_VERIFICATION_CODE_TTL', 10),

];
