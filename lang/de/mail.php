<?php

/*
|--------------------------------------------------------------------------
| Texte der Systemmails
|--------------------------------------------------------------------------
|
| Konto, Einladung und Abonnement -- alles, was nicht zu einem Fachbereich mit
| eigener Sprachdatei gehoert. Die Vorlagen des Starterkits brachten ihre Texte
| als englische Schluessel mit; hier stehen sie als Bausteine, damit Betreff und
| Inhalt einer Mail an einer Stelle gepflegt werden.
|
| Angesprochen als __('mail.<bereich>.<schluessel>').
|
*/

return [

    'support' => 'Fragen? Schreib uns an :email.',
    'closing' => 'Viele Grüße, dein :app-Team',
    'fallback' => 'Falls der Knopf nicht funktioniert, kopiere diese Adresse in die Adresszeile deines Browsers:',

    'verify_email' => [
        'subject' => 'Bestätige deine E-Mail-Adresse',
        'label' => 'Konto',
        'heading' => 'E-Mail-Adresse bestätigen',
        'intro' => 'Willkommen bei :app. Bestätige deine E-Mail-Adresse, dann steht dir dein Konto offen.',
        'cta' => 'E-Mail-Adresse bestätigen',
    ],

    'reset_password' => [
        'subject' => 'Neues Passwort vergeben',
        'label' => 'Konto',
        'heading' => 'Passwort zurücksetzen',
        'intro' => 'Für dein Konto wurde ein neues Passwort angefordert. Über den Knopf vergibst du es.',
        'cta' => 'Passwort zurücksetzen',
        'expiry' => 'Der Link gilt 60 Minuten.',
        'ignore' => 'Hast du die Anfrage nicht gestellt, ist nichts weiter zu tun.',
    ],

    'otp' => [
        'label' => 'Anmeldung',
        'heading' => 'Dein Anmeldecode',
        'intro' => 'Mit diesem Code meldest du dich auf :url an.',
        'warning' => 'Gib den Code an niemanden weiter. Hast du die Anmeldung nicht ausgelöst, ignoriere diese E-Mail.',
    ],

    'invitation' => [
        'subject' => 'Einladung zu :tenant',
        'label' => 'Einladung',
        'heading' => 'Du wurdest zu :tenant eingeladen',
        'intro' => 'Du wurdest eingeladen, im Workspace ":tenant" mitzuarbeiten. Über den Knopf nimmst du die Einladung an.',
        'cta' => 'Einladung annehmen',
    ],

    'subscribed' => [
        'subject' => 'Willkommen bei :app',
        'label' => 'Abonnement',
        'heading' => 'Willkommen bei :app',
        'intro' => 'Dein Abonnement des Tarifs ":plan" ist aktiv. Schön, dass du dabei bist.',
        'feedback' => 'Wenn dir etwas fehlt oder auffällt, schreib uns. Rückmeldungen bestimmen, woran wir als Nächstes arbeiten.',
    ],

    'subscription_cancelled' => [
        'subject' => 'Dein Abonnement wurde gekündigt',
        'label' => 'Abonnement',
        'heading' => 'Schade, dass du gehst',
        'intro' => 'Dein Abonnement ist gekündigt. Sag uns gern, was wir besser machen können.',
        'return' => 'Du kannst jederzeit wieder abonnieren, direkt aus deinem Konto heraus.',
        'thanks' => 'Danke, dass du dabei warst.',
    ],

    'payment_failed' => [
        'subject' => 'Deine Zahlung ist fehlgeschlagen',
        'label' => 'Abonnement',
        'heading' => 'Zahlung fehlgeschlagen',
        'greeting' => 'Hallo :name,',
        'intro' => 'Die Zahlung für dein Abonnement ":plan" konnten wir nicht verarbeiten. Hinterlege eine andere Zahlungsart, sonst endet der Zugang.',
        'cta' => 'Zahlungsart aktualisieren',
    ],

    'expiring_soon' => [
        'subject' => 'Dein Abonnement läuft bald aus',
        'label' => 'Abonnement',
        'heading' => 'Dein Abonnement läuft bald aus',
        'greeting' => 'Hallo :name,',
        'intro' => 'Dein Abonnement des Tarifs ":plan" läuft in Kürze aus. Schließe es ab, damit der Zugang bestehen bleibt.',
        'cta' => 'Abonnement abschließen',
    ],

    'referral_reward' => [
        'subject' => 'Deine Empfehlung hat sich gelohnt',
        'label' => 'Empfehlung',
        'heading' => 'Du hast eine Prämie erhalten',
        'intro' => ':name ist über deine Empfehlung dazugekommen. Dafür gibt es einen Gutscheincode.',
        'code_heading' => 'Dein Gutscheincode',
        'more' => 'Empfiehl uns weiter, jede angenommene Empfehlung bringt eine weitere Prämie.',
        'cta' => 'Zum Konto',
    ],

];
