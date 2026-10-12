<?php

declare(strict_types=1);

/**
 * Texte des Portals (Portal Phase 1).
 *
 * Die Seitentitel stehen als Schluessel in den Routen und werden erst in der
 * Ansicht uebersetzt -- ein uebersetzter Text in der Routendatei wuerde beim
 * Zwischenspeichern der Routen in der dann geltenden Sprache einfrieren.
 */
return [
    'label' => 'Portal',

    'pages' => [
        'overview' => 'Uebersicht',
        'marketplace' => 'Marktplatz',
        'leads' => 'Gekaufte Leads',
        'lead_detail' => 'Lead-Detail',
        'wallet' => 'Guthaben aufladen',
        'transactions' => 'Transaktionen',
        'buyer_profile' => 'Kaeuferprofil',
        'buying_criteria' => 'Kaufkriterien',
        'caller_id' => 'Eigene Rufnummer',
        'orders' => 'Bestellungen',
        'funnels' => 'Funnels',
        'earnings' => 'Einnahmen',
        'payout' => 'Auszahlung',
        'reports' => 'Berichte',
        'settings' => 'Einstellungen',
    ],

    /*
    |--------------------------------------------------------------------------
    | Beschriftung der Illustrationen (LP-ONBOARD-006)
    |--------------------------------------------------------------------------
    |
    | Die Vorgabetexte von x-ui.illustration. Sie sind das Einzige, was blinde
    | Nutzer von den Motiven hoeren -- sie beschreiben also, was zu sehen ist,
    | nicht wozu das Bild dasteht. Fehlt ein Schluessel, laesst die Komponente
    | das Bild dekorativ statt den Schluesselnamen vorzulesen.
    */

    'illustrations' => [
        'phone' => 'Smartphone mit einer eingehenden Nachricht',
        'sms' => 'Kurznachricht mit einem sechsstelligen Code',
        'success' => 'Haken im Kreis: erledigt',
        'industries' => 'Drei Branchenkacheln, eine davon gewählt',
        'region' => 'Umriss von Deutschland mit Ort und Umkreis',
        'payment' => 'Geldbeutel mit Karte und Münzen',
        'lead-preview' => 'Anfrage mit gesperrter Rufnummer',
        'checklist' => 'Klemmbrett mit drei abgehakten Schritten',
        'call' => 'Telefonhörer mit Stoppuhr',
    ],

    /*
    |--------------------------------------------------------------------------
    | Onboarding-Gate: die Rufnummer (LP-ONBOARD-003)
    |--------------------------------------------------------------------------
    */

    'phone_gate' => [
        'text' => 'Bevor du den Marktplatz siehst, bestätige bitte deine Rufnummer. Ohne sie kannst du einen gekauften Lead nicht anrufen -- und ein Lead gilt erst mit dem Anruf als bearbeitet.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rufnummernbestaetigung (LP-ONBOARD-004)
    |--------------------------------------------------------------------------
    |
    | Die Meldungen des Dienstes App\Services\Phone\PhoneVerificationService.
    | Sie stehen direkt vor dem Kaeufer, der gerade nicht weiterkommt -- jede
    | sagt deshalb, was er tun kann, und keine nennt einen Fehlercode.
    |
    | Die Meldung zur fremden Nummer sagt bewusst nicht, welches Konto sie hat:
    | Mit einer Liste von Nummern liesse sich sonst eine Kundenliste
    | zusammentragen.
    */

    'phone_verification' => [
        'errors' => [
            'unrecognized' => 'Diese Nummer können wir nicht erkennen. Prüf die Schreibweise.',
            'taken' => 'Diese Nummer gehört schon zu einem anderen Konto. Melde dich beim Support.',
            'too_many_requests' => 'Für diese Nummer haben wir schon mehrere Codes verschickt. Versuch es in :wait noch einmal.',
            'too_many_checks' => 'Zu viele Versuche. Warte :wait und gib den Code dann erneut ein.',
            'resend_too_early' => 'Der Code ist unterwegs. In :seconds Sekunden kannst du ihn erneut anfordern.',
            'call_requires_sms' => 'Den Code am Telefon ansagen können wir erst, wenn die SMS nicht angekommen ist. Fordere zuerst eine SMS an.',
            'unavailable' => 'Wir können gerade keinen Code verschicken. Versuch es in ein paar Minuten noch einmal.',
        ],

        'wait' => [
            'seconds' => '{1} einer Sekunde|[2,*] :count Sekunden',
            'minutes' => '{1} einer Minute|[2,*] :count Minuten',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Einrichtungs-Wizard (LP-ONBOARD-007)
    |--------------------------------------------------------------------------
    |
    | Die Huelle und die Beschriftungen der vier Schritte. Die Texte der Felder
    | selbst stehen bei den jeweiligen Schritten (LP-ONBOARD-008/009/010).
    */

    'onboarding' => [
        /*
        |----------------------------------------------------------------------
        | Rufnummernbestaetigung, die drei Screens (LP-ONBOARD-005)
        |----------------------------------------------------------------------
        |
        | Der erste Bildschirm eines neuen Kaeufers. Die Texte duzen und
        | erklaeren jeweils den Grund, nicht die Regel: "Mit dieser Nummer
        | rufst du spaeter deine Leads an" sagt, warum wir fragen -- "zur
        | Verifizierung erforderlich" sagt nur, dass wir duerfen.
        |
        | Die Fehlermeldungen des Dienstes stehen NICHT hier, sondern unter
        | portal.phone_verification.errors: Sie gelten auch fuer den Admin und
        | fuer kuenftige Wege auf dieselbe Pruefung. Hier stehen nur die, die
        | es allein auf diesem Screen gibt.
        */
        'phone' => [
            'title' => 'Telefonnummer bestätigen',
            'welcome' => 'Willkommen bei widileads, :name',

            'number' => [
                'heading' => 'Bestätige deine Telefonnummer',
                'text' => 'Mit dieser Nummer rufst du später deine Leads an. Wir schicken dir einen 6-stelligen Code.',
                'country' => 'Ländervorwahl',
                'field' => 'Telefonnummer',
                'placeholder' => '172 1234567',
                'submit' => 'Code senden',
                'sending' => 'Wird gesendet ...',
            ],

            'code' => [
                'heading' => 'Code eingeben',
                'sent_sms' => 'Gesendet an :number',
                'sent_call' => 'Angesagt unter :number',
                'edit' => 'Nummer ändern',
                'field' => 'Bestätigungscode',
                'digit' => 'Ziffer :position von 6',
                'submit' => 'Bestätigen',
                'checking' => 'Wird geprüft ...',
                'resend_in' => 'Nichts bekommen? Erneut senden in :countdown',
                'resend' => 'Code erneut senden',
                'resending' => 'Wird gesendet ...',
                'call_instead' => 'Stattdessen anrufen lassen',
                'blocked_in' => 'Wieder möglich in :countdown',
            ],

            'success' => [
                'heading' => 'Nummer bestätigt',
                'text' => 'Jetzt richten wir in drei kurzen Schritten ein, welche Anfragen du sehen willst.',
                'submit' => 'Los geht\'s',
            ],

            'errors' => [
                'required' => 'Bitte gib deine Telefonnummer ein.',
                'code_incomplete' => 'Der Code hat sechs Ziffern. Es fehlt noch mindestens eine.',
                'code_wrong' => 'Der Code stimmt nicht. Versuch es noch einmal.',
                'code_expired' => 'Der Code ist abgelaufen. Wir schicken dir einen neuen.',
            ],
        ],

        'wizard' => [
            'title' => ':counter - :step',
            'counter' => 'Schritt :step von :total',
            'rail_label' => 'Einrichtung',
            'back' => 'Zurück',
            'next' => 'Weiter',
            'finish' => 'Weiter zur Zahlung',
            'working' => 'Einen Moment ...',
            'skip' => 'Später einrichten',
            'step_pending' => 'Dieser Schritt wird gerade gebaut. Mit "Weiter" überspringst du ihn vorläufig.',
            'state' => [
                'done' => 'erledigt',
                'active' => 'aktueller Schritt',
                'open' => 'offen',
            ],
        ],

        'steps' => [
            'phone' => [
                'label' => 'Rufnummer',
            ],
            'industries' => [
                'label' => 'Branchen',
                'heading' => 'Welche Anfragen willst du kaufen?',
                'text' => 'Wähl eine oder mehrere Branchen. Du kannst das jederzeit ändern.',
            ],
            'region' => [
                'label' => 'Region',
                'heading' => 'Wo sollen die Anfragen herkommen?',
                'text' => 'Deutschlandweit siehst du am meisten. Wenn du nur in deiner Gegend arbeitest, schränk hier ein.',
            ],
            'payment' => [
                'label' => 'Zahlung',
                'heading' => 'Wie willst du bezahlen?',
                'text' => 'Guthaben im Voraus oder Pay as you go am Monatsende. Bezahlt wird immer erst, wenn du einen Lead kaufst.',
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | Die Beispiel-Lead-Karte (x-app.lead-card variant="demo")
        |----------------------------------------------------------------------
        |
        | Gebraucht im Abschluss-Screen und in der Tour. Der Satz, wann die
        | Rufnummer frei wird, steht NICHT hier: Er kommt aus dem
        | LeadPresenter (call.phone_release.*), damit in Beispiel und Ernstfall
        | derselbe Satz steht.
        */
        'lead_card' => [
            'example' => 'Beispiel',
        ],

        /*
        |----------------------------------------------------------------------
        | Schritt 1: Branchen (LP-ONBOARD-008)
        |----------------------------------------------------------------------
        |
        | Die Zahl auf der Kachel ist eine Groessenordnung, deshalb steht ein
        | "ca." davor und unter der Mindestzahl gar keine Zahl, sondern "neu".
        | Die Summe in der Live-Zeile traegt {count} statt :count, weil sie im
        | Browser eingesetzt wird (Alpine) und nicht von __().
        */
        'industries' => [
            'weekly' => 'ca. :count Anfragen / Woche',
            'fresh' => 'neu',
            'show_all' => 'Alle Branchen anzeigen',
            'summary' => 'Mit dieser Auswahl siehst du ca. {count} neue Anfragen pro Woche.',
            'error' => 'Wähl mindestens eine Branche, damit wir dir Anfragen zeigen können.',

            'empty' => [
                'title' => 'Noch keine Branchen im Angebot',
                'text' => 'Gerade ist kein Portal freigeschaltet, aus dem wir dir Anfragen zeigen könnten. Geh weiter -- sobald eines dazukommt, wählst du es in den Kaufkriterien.',
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | Schritt 2: Region (LP-ONBOARD-009)
        |----------------------------------------------------------------------
        |
        | Deutschlandweit ist die Vorgabe und das Verkaufsargument -- die Texte
        | sagen das, ohne den Umkreis schlechtzureden: Wer einschraenkt, soll
        | wissen, was er aufgibt, und nicht, dass er falsch liegt.
        */
        'region' => [
            'recommended' => 'Empfohlen',

            'nationwide' => [
                'title' => 'Ganz Deutschland',
                'text' => 'Die meisten Anfragen, kein Streuverlust',
            ],

            'radius' => [
                'title' => 'Nur in meiner Region',
                'text' => 'PLZ und Umkreis festlegen',
            ],

            'plz' => [
                'label' => 'Postleitzahl',
                'placeholder' => '76133',
            ],

            'radius_field' => [
                'label' => 'Umkreis',
                'unit' => 'km',
            ],

            'summary' => 'In diesem Gebiet gab es zuletzt ca. :count Anfragen pro Woche in deinen Branchen.',
            'empty' => 'Hier kommen bisher wenige Anfragen an. Ein größerer Umkreis lohnt sich.',

            'errors' => [
                'unknown_plz' => 'Diese PLZ kennen wir nicht. Prüf die fünf Ziffern.',
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | Schritt 3: Zahlungsweise (LP-ONBOARD-010)
        |----------------------------------------------------------------------
        |
        | "reicht fuer ca. n Leads" ist eine Orientierung am mittleren
        | Leadpreis der gewaehlten Branchen -- deshalb "ca." und deshalb kein
        | Betrag im Satz. Der Hinweis unter den Karten nennt die eine Regel,
        | die der Kaeufer vor dem Bezahlen kennen muss: Geld kostet nur ein
        | angenommener Anruf.
        */
        'payment' => [
            'amount' => ':amount €',
            'popular' => 'Beliebt',
            'covers' => '{0} reicht für keinen Lead|{1} reicht für ca. einen Lead|[2,*] reicht für ca. :count Leads',
            'more_leads' => '+:count',
            'later' => 'Später, erst mal umschauen',
            'continue' => 'Weiter zur Einrichtung',
            'error' => 'Wähl ein Paket oder Pay as you go, damit du Leads kaufen kannst.',

            'payg' => [
                'title' => 'Pay as you go: nur zahlen, was du kaufst',
                'text' => 'Kein Guthaben im Voraus. Deine Käufe werden gesammelt und später abgebucht.',
                'hint' => 'Du hinterlegst dafür ein Zahlungsmittel. Je Kauf kommt ein Aufschlag von :percent % dazu.',
            ],

            'billing' => [
                'note' => 'Du zahlst nur für Leads, bei denen der Anruf angenommen wird.',
                'link' => 'Wie wird das gezählt?',
                'heading' => 'So rechnen wir ab',
                'close' => 'Schließen',
                'understood' => 'Verstanden',
            ],
        ],

        /*
         | Der Abschluss-Screen (LP-ONBOARD-011)
         |------------------------------------------------------------------
         |
         | Die letzte Seite vor dem Marktplatz: der Beispiel-Lead mit seinen
         | drei Markern und der 3-Schritte-Player. Die Zahlen in `explainer`
         | und `deadline` setzt die Komponente aus config('lead_calls.*') ein
         | -- hier steht kein Schwellwert als Text.
         |
         | Der Produktname steht ausgeschrieben und nicht als :app aus
         | config('app.wordmark'): Die Wortmarke dort ist die des Betreibers
         | (widimedia), der Kaeufer ruft aber ueber widileads an.
         */
        'done' => [
            'title' => 'Fast fertig',
            'heading' => 'Fast fertig - so funktioniert ein Lead',
            'text' => 'Ein Blick auf eine Beispiel-Anfrage, dann bist du im Marktplatz.',

            'demo' => [
                'caption' => 'Keine echte Anfrage',
                'name' => 'Max Beispiel',
                'funnel' => 'Elektriker',
                'chips' => [
                    'Zählerschrank tauschen',
                    'Einfamilienhaus',
                    'In 2 Wochen',
                ],
                'phone_hint' => 'Vollständig sichtbar nach dem Kauf.',
            ],

            'markers' => [
                'close' => 'Hinweis schließen',
                'chips' => [
                    'label' => 'Was bedeuten die Merkmale?',
                    'hint' => 'Die Merkmale kommen aus dem Formular, das der Interessent ausgefüllt hat. Daran siehst du vor dem Kauf, ob die Anfrage zu dir passt.',
                ],
                'phone' => [
                    'label' => 'Warum ist die Nummer verdeckt?',
                    'hint' => 'Die vollständige Rufnummer bekommst du mit dem Kauf. Vorher sieht sie kein Käufer -- auch du nicht.',
                ],
                'price' => [
                    'label' => 'Wann wird der Preis abgebucht?',
                    'hint' => 'Der Preis wird beim Kauf reserviert und erst abgebucht, wenn du den Interessenten wirklich am Telefon hattest.',
                ],
            ],

            'explainer' => [
                'heading' => 'So wird abgerechnet',
                'counter' => 'Schritt :step von :total',
                'balance' => 'Guthaben',
                'next' => 'Weiter',
                'restart' => 'Von vorn',
                'go_to' => 'Zu Schritt :step',
                'steps' => [
                    'ringing' => [
                        'title' => 'Du rufst über widileads an',
                        'text' => 'Den Anruf startest du im Portal. Deine eigene Nummer bleibt dabei unsichtbar.',
                    ],
                    'answered' => [
                        'title' => 'Anruf wird angenommen',
                        'text' => 'Ab :seconds Sekunden Gespräch wird der Lead berechnet: :amount gehen vom Guthaben ab.',
                    ],
                    'missed' => [
                        'title' => 'Nicht erreicht?',
                        'text' => 'Nach :attempts Versuchen an :days Tagen mit mindestens :hours Stunden Abstand ist der Lead kostenlos -- du bekommst den Betrag zurück.',
                    ],
                ],
            ],

            'deadline' => 'Du hast :days Tage Zeit. Weniger als :attempts Versuche bis zum Fristende zählen als erreicht.',
            'to_marketplace' => 'Zum Marktplatz',
            'top_up_first' => 'Erst Guthaben aufladen',
        ],

        /*
        |----------------------------------------------------------------------
        | Die Aktivierungskarte im Dashboard (LP-ONBOARD-012)
        |----------------------------------------------------------------------
        |
        | Die Karte fuehrt vom Ende des Wizards bis zum ersten gefuehrten
        | Anruf. Die Zeilen sind Feststellungen, keine Aufforderungen -- der
        | Kaeufer soll sehen, was schon steht. Nur die eine Zeile, die der
        | uebersprungene Wizard offen gelassen hat, fordert auf
        | (steps.criteria_todo), und nur der naechste offene Schritt hat einen
        | Knopf.
        */
        'activation' => [
            'heading' => 'Deine ersten Schritte',
            'progress' => ':done von :total',
            'hide' => 'Ausblenden',

            'steps' => [
                'phone' => 'Telefonnummer bestätigt',
                'criteria' => 'Branchen und Region gewählt',
                'criteria_todo' => 'Branchen und Region festlegen',
                'topup' => 'Guthaben aufgeladen',
                'purchase' => 'Ersten Lead gekauft',
                'call' => 'Ersten Anruf geführt',
            ],

            'actions' => [
                'criteria' => 'Branchen wählen',
                'topup' => 'Guthaben aufladen',
                'purchase' => 'Zum Marktplatz',
                'call' => 'Jetzt anrufen',
            ],

            'activated' => [
                'heading' => 'Du bist startklar.',
                'text' => 'Erster Anruf erledigt -- ab hier zählt nur noch, wen du erreichst. Diese Karte siehst du nicht wieder.',
            ],
        ],
    ],

    'placeholder' => [
        'text' => 'Diese Seite wird gerade gebaut. Bis sie fertig ist, findest du die Funktion im gewohnten Bereich.',
        'link' => 'Zum bisherigen Bereich',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rahmen des Arbeitsbereichs
    |--------------------------------------------------------------------------
    |
    | Kopf, Navigation, leerer Zustand und Blaetterleiste. Seiteninhalte stehen
    | in der Sprachdatei ihres eigenen Themas (marketplace.php, leads.php).
    |
    */

    'close' => 'Schließen',
    'skip_to_content' => 'Zum Inhalt springen',
    'open_menu' => 'Menü öffnen',
    'account' => 'Konto',
    'balance' => 'Guthaben',
    'top_up' => 'Aufladen',

    /*
    |--------------------------------------------------------------------------
    | Profil
    |--------------------------------------------------------------------------
    |
    | Das persoenliche Konto. Die Rechnungsanschrift gehoert dem Workspace und
    | steht deshalb in den Einstellungen, nicht hier.
    |
    */
    'profile' => [
        'heading' => 'Profil',
        'description' => 'Dein Name, deine Anmeldedaten und die Sicherheit deines Kontos.',
        'save' => 'Speichern',

        'account' => [
            'heading' => 'Konto',
            'name' => 'Name',
            'email' => 'E-Mail-Adresse',
            'current_password' => 'Aktuelles Passwort',
            'password_required' => 'Zum Ändern der E-Mail-Adresse brauchen wir dein aktuelles Passwort.',
            'password_wrong' => 'Das Passwort stimmt nicht.',
            'email_warning' => 'Die neue Adresse muss bestätigt werden. Wir schicken einen Link dorthin; bis du ihn anklickst, kommst du nicht ins Portal.',
            'email_changed' => 'Adresse geändert. Bestätige sie über den Link in deinem Postfach.',
            'unverified' => 'Deine Adresse ist noch nicht bestätigt.',
            'resend' => 'Link erneut senden',
            'verification_sent' => 'Bestätigungslink verschickt.',
            'verification_throttled' => 'Der Link wurde eben erst verschickt. Bitte warte einen Moment.',
        ],

        'password' => [
            'heading' => 'Passwort ändern',
            'description' => 'Nach dem Ändern bleibst du angemeldet.',
            'new' => 'Neues Passwort',
            'confirm' => 'Neues Passwort wiederholen',
            'hint' => 'Mindestens 8 Zeichen.',
            'mismatch' => 'Die beiden Passwörter stimmen nicht überein.',
            'submit' => 'Passwort ändern',
            'saved' => 'Passwort geändert.',
        ],

        'two_factor' => [
            'heading' => 'Zwei-Faktor-Anmeldung',
            'active' => 'Aktiv. Beim Anmelden fragen wir zusätzlich nach einem Code.',
            'inactive' => 'Nicht eingerichtet. Ein zweiter Faktor schützt dein Konto, wenn dein Passwort einmal bekannt wird.',
            'manage' => 'Verwalten',
            'setup' => 'Einrichten',
        ],

        'side' => [
            'status' => 'Adresse',
            'verified' => 'Bestätigt',
            'unverified' => 'Offen',
            'member_since' => 'Dabei seit',
            'workspace' => 'Workspace',
            'address_hint' => 'Die Rechnungsanschrift gehört zum Workspace und steht unter',
        ],
    ],

    'menu' => [
        'profile' => 'Profil',
        'settings' => 'Einstellungen',
        'logout' => 'Abmelden',
    ],

    'workspace' => [
        'buyer' => 'Käufer-Workspace',
        'seller' => 'Verkäufer-Workspace',
        'switch' => 'Workspace wechseln',
    ],

    'nav' => [
        'label' => 'Hauptnavigation',
        'dashboard' => 'Dashboard',
        'my_leads' => 'Meine Leads',
        'team_leads' => 'Team Leads',
        'marketplace' => 'Marktplatz',
        'buying_criteria' => 'Kaufkriterien',
        'caller_id' => 'Rufnummer',
        'balance' => 'Guthaben',
        'orders' => 'Bestellungen',
        'payments' => 'Zahlungen',
        'transactions' => 'Transaktionen',
        'soon' => 'Diese Seite wird gerade gebaut.',
        'group' => [
            'marketplace' => 'Marktplatz',
            'billing' => 'Abrechnung',
        ],
    ],

    'filters' => [
        'label' => 'Filter',
        'sort' => 'Sortierung',
    ],

    'toast' => [
        'dismiss' => 'Meldung schließen',
        'saved' => 'Gespeichert.',
        'discarded' => 'Änderungen verworfen.',
        'note_saved' => 'Notiz gespeichert.',
        'status_saved' => 'Status gespeichert.',
    ],
    'pagination' => [
        'label' => 'Seiten',
        'previous' => 'Zurück',
        'next' => 'Weiter',
        'page' => 'Seite :page',
    ],

    'empty' => [
        'title' => 'Nichts gefunden',
        'description' => 'Hier ist im Moment nichts zu sehen.',
    ],
    'topup' => [
        'back' => 'Zurück zum Marktplatz',
        'back_onboarding' => 'Zurück zur Einrichtung',
        'heading' => 'Guthaben aufladen',
        'description' => 'Mit deinem Guthaben kaufst du Leads im Marktplatz. Es verfällt nicht.',
        'balance_label' => 'Aktuelles Guthaben',
        'form_heading' => 'Aufladung',
        'form_hint' => 'Ein Lead kostet :price. Berechnet wird er erst, wenn du den Anfragenden erreichst.',
        'packages_legend' => 'Wie viel möchtest du aufladen?',
        'package_leads' => '{0} reicht für keinen Lead|{1} reicht für einen Lead|[2,*] reicht für :count Leads',
        'popular' => 'Beliebt',
        'custom_toggle' => 'Anderen Betrag eingeben',
        'custom_label' => 'Betrag in Euro',
        'custom_placeholder' => 'z. B. :amount',
        'custom_hint' => 'Mindestens :min €, höchstens :max €. Nur ganze Euro.',
        'workspace_label' => 'Für welche Firma?',
        'subtotal' => 'Zwischensumme',
        'vat' => 'davon :percent % USt.',
        'total' => 'Gesamt',
        'vat_note' => 'Alle Preise inklusive Umsatzsteuer.',
        'after' => 'Guthaben nach Aufladung',
        'submit' => 'Jetzt :amount zahlen',
        'legal' => 'Mit dem Kauf stimmst du den :terms und der :privacy zu.',
        'legal_terms' => 'AGB',
        'legal_privacy' => 'Datenschutzerklärung',
        'payment_hint' => 'Die Zahlung wird sicher über den Zahlungsanbieter des Portals abgewickelt.',
        'rules' => [
            'heading' => 'So rechnen wir ab',
            'items' => [
                'Beim Kauf wird der Leadpreis nur reserviert.',
                'Erreichst du den Anfragenden (ab :seconds Sekunden Gespräch), wird abgebucht.',
                'Nach :attempts Versuchen an :days Tagen ohne Gespräch wird der Betrag wieder frei.',
                'Guthaben verfällt nicht, die Rechnung kommt sofort per E-Mail.',
            ],
            'link' => 'Alle Abrechnungsregeln',
        ],
        'recent' => [
            'heading' => 'Letzte Aufladungen',
            'link' => 'Alle Buchungen',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard (Portal Phase 1)
    |--------------------------------------------------------------------------
    |
    | Gebaut nach einer Frage: Was ist jetzt zu tun? Deshalb sprechen die Texte
    | von Handlungen und nicht von Kennzahlen.
    |
    */

    'dashboard' => [

        'greeting' => [
            'morning' => 'Guten Morgen, :name',
            'day' => 'Hallo, :name',
            'evening' => 'Guten Abend, :name',
        ],

        'summary' => '{0} Heute ist kein Anruf fällig, :leads neue Leads passen zu deinen Kriterien.|{1} 1 Anruf ist offen, :leads neue Leads passen zu deinen Kriterien.|[2,*] :calls Anrufe sind offen, :leads neue Leads passen zu deinen Kriterien.',

        'top_up' => 'Guthaben aufladen',
        'to_marketplace' => 'Zum Marktplatz',

        'kpi' => [
            'balance' => 'Verfügbares Guthaben',
            'reserved' => ':amount reserviert',
            'new_leads' => 'Neue passende Leads',
            'new_leads_meta' => 'seit gestern · :count insgesamt',
            'open_calls' => 'Offene Anrufe',
            'due_today' => '{0} keine Frist endet heute|{1} 1 Frist endet heute|[2,*] :count Fristen enden heute',
            'reached' => 'Erreicht (30 Tage)',
            'reached_value' => ':captured von :decided',
            'reached_meta' => ':rate % · :released nicht berechnet',
        ],

        'deadlines' => [
            'heading' => 'Frist läuft ab',
            'all' => 'Alle offenen',
            'empty' => 'Kein Lead wartet gerade auf einen Anruf.',
            'today' => 'heute, :time',
            'in_days' => '{1} morgen|[2,*] in :count Tagen',
            'over' => 'Frist abgelaufen',
            'none' => 'ohne Frist',
            'meta' => ':region · :done von :total Versuchen',
            'call' => 'Jetzt anrufen',
            'rule' => 'Ohne drei Versuche bis zur Frist wird der Lead berechnet. Erreichst du niemanden, wird der Betrag freigegeben.',
        ],

        'suggestions' => [
            'heading' => 'Neue Leads für dich',
            'all' => 'Alle im Marktplatz',
            'empty' => 'Zurzeit passt kein Lead zu deinen Kriterien.',
            'badge' => 'Neu',
            'view' => 'Ansehen',
        ],

        'stats' => [
            'heading' => 'Letzte 30 Tage',
            'range' => ':from – :to',
            'bought' => 'Gekauft',
            'bought_value' => '{0} keine Leads|{1} 1 Lead|[2,*] :count Leads',
            'captured' => 'Berechnet',
            'captured_meta' => '{0} kein Lead|{1} 1 Lead|[2,*] :count Leads',
            'released' => 'Freigegeben',
            'released_meta' => '{0} kein Lead nicht erreicht|{1} 1 Lead nicht erreicht|[2,*] :count Leads nicht erreicht',
            'rate' => 'Erreichbarkeitsquote',
            'average' => 'Durchschnitt aller Käufer: :rate %',
        ],

        'criteria' => [
            'heading' => 'Deine Kaufkriterien',
            'action' => 'Kriterien anpassen',
        ],

        'activity' => [
            'heading' => 'Zuletzt passiert',
            'all' => 'Ganzer Verlauf',
            'empty' => 'Noch nichts passiert.',
            'call' => 'Anruf bei einem Lead',
            'topup' => 'Guthaben aufgeladen – :amount',
            'reserve' => 'Lead gekauft – :amount reserviert',
            'capture' => 'Lead erreicht – :amount berechnet',
            'release' => 'Lead nicht erreicht – :amount freigegeben',
            'refund' => 'Erstattung – :amount',
            'earning' => 'Einnahme – :amount',
            'commission' => 'Provision – :amount',
            'payout' => 'Auszahlung – :amount',
            'adjustment' => 'Korrektur – :amount',
            'opening_balance' => 'Eröffnungssaldo – :amount',
        ],

        'caller_id' => [
            'heading' => 'Rufnummer',
            'default' => 'Standard für Anrufe über :app',
            'action' => 'Weitere Nummer bestätigen',
            'missing' => 'Noch keine bestätigte Rufnummer. Ohne sie kannst du keine Leads anrufen.',
            'verify' => 'Rufnummer bestätigen',
        ],

        'when' => [
            'today' => 'heute, :time',
            'yesterday' => 'gestern, :time',
            'on' => ':date, :time',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pay as you go im Portal (LP-POSTPAID-010)
    |--------------------------------------------------------------------------
    |
    | Antrag, Zahlungsmittel, offener Betrag und Abrechnungen. Die Texte des
    | Verfahrens selbst -- Mandatswortlaut, Fehlermeldungen der Dienste,
    | Eignungsgruende -- stehen weiterhin in marketplace.php und werden hier
    | nicht zweitgeschrieben.
    |
    */

    'postpaid' => [

        'label' => 'Pay as you go',

        'apply' => [
            'heading' => 'Pay as you go',
            'description' => 'Kaufe Leads ohne Vorauszahlung und zahle im Nachhinein. Wir ziehen deinen offenen Betrag automatisch ein.',
            'points' => [
                'Du kaufst gegen einen Kreditrahmen statt aus dem Guthaben.',
                'Dein Rahmen liegt anfangs bei :limit.',
                'Eingezogen wird jeden :weekday, spätestens ab :threshold offen.',
                'Auf jeden Leadpreis kommt ein Aufschlag von :percent %.',
            ],
            'checklist_heading' => 'Das prüfen wir vorher',
            'rule_met' => 'Erfüllt',
            'rule_open' => 'Noch offen',
            'rules' => [
                'purchases' => 'Mindestens :required abgerechnete Leadkäufe – du hast :count.',
                'account_age' => 'Konto mindestens :required Tage alt – deins ist :days Tage alt.',
                'history' => 'Keine Zahlungsstörung in den letzten :days Tagen.',
                'not_blocked' => 'Keine Kaufsperre auf deinem Konto.',
                'payment_method' => 'Ein Zahlungsmittel ist hinterlegt.',
            ],
            'submit' => 'Pay as you go beantragen',
            'payment_method_cta' => 'Zahlungsmittel hinterlegen',
            'payment_method_hint' => 'Ohne hinterlegtes Zahlungsmittel können wir nichts einziehen.',
            'not_eligible_hint' => 'Sobald alle Punkte erfüllt sind, kannst du den Antrag stellen.',
            'pending_heading' => 'Antrag eingereicht',
            'pending_text' => 'Wir haben deinen Antrag am :date erhalten und melden uns per E-Mail.',
            'rejected_heading' => 'Abgelehnt',
            'rejected_text' => 'Dein letzter Antrag wurde abgelehnt.',
            'rejected_retry' => 'Du kannst in :days Tagen erneut beantragen.',
            'rejected_retry_now' => 'Du kannst jetzt erneut beantragen.',
            'submitted' => 'Dein Antrag ist eingegangen. Wir melden uns per E-Mail.',
        ],

        'balance' => [
            'open_label' => 'Offener Betrag',
            'remaining_label' => 'Noch verfügbar',
            'remaining_value' => ':available von :limit',
            'tooltip' => 'Der offene Betrag wird jeden :weekday bzw. ab :threshold automatisch eingezogen.',
            'exhausted' => 'Dein Kreditrahmen ist erschöpft. Der offene Betrag wird beim nächsten Einzug ausgeglichen.',
            'surcharge_hint' => 'inkl. :percent % Pay-as-you-go-Aufschlag',
            'settlements_link' => 'Abrechnungen ansehen',
        ],

        'blocked' => [
            'heading' => 'Dein Konto ist gesperrt.',
            'text' => 'Offener Betrag: :amount',
            'action' => 'Jetzt ausgleichen',
        ],

        'payment_methods' => [
            'heading' => 'Zahlungsmittel',
            'description' => 'Über dieses Mittel ziehen wir deinen offenen Betrag ein. IBAN und Kartennummer gibst du direkt beim Zahlungsanbieter ein.',
            'empty_title' => 'Noch kein Zahlungsmittel',
            'empty_description' => 'Hinterlege ein Zahlungsmittel, damit wir deinen offenen Betrag einziehen können.',
            'add' => 'Zahlungsmittel hinzufügen',
            'cancel' => 'Abbrechen',
            'remove' => 'Entfernen',
            'remove_confirm' => 'Dieses Zahlungsmittel wirklich entfernen?',
            'removed' => 'Zahlungsmittel entfernt.',
            'added' => 'Zahlungsmittel hinterlegt.',
            'make_default' => 'Als Standard festlegen',
            'default_set' => 'Standard geändert.',
            'default_badge' => 'Standard',
            'mandate_since' => 'Mandat seit :date',
            'steps' => [
                'type' => 'Art wählen',
                'details' => 'Daten eingeben',
                'mandate' => 'Mandat bestätigen',
                'done' => 'Fertig',
            ],
            'step_of' => 'Schritt :step von :total',
            'type_heading' => 'Womit möchtest du zahlen?',
            'details_heading' => 'Deine Zahlungsdaten',
            'details_hint' => 'Die Eingabe läuft direkt beim Zahlungsanbieter. Wir speichern nur die letzten vier Stellen.',
            'mandate_heading' => 'SEPA-Lastschriftmandat',
            'done_heading' => 'Zahlungsmittel hinterlegt',
            'done_text' => 'Wir ziehen deinen offenen Betrag künftig über :method ein.',
            'back' => 'Zurück',
            'next' => 'Weiter',
            'save' => 'Zahlungsmittel speichern',
            'close' => 'Fertig',
            'unavailable' => 'Die Zahlungsdaten-Eingabe konnte nicht geladen werden. Versuche es bitte später erneut.',
        ],

        'settlements' => [
            'heading' => 'Abrechnungen',
            'description' => 'Jeder Einzug deines offenen Betrags mit Datum, Betrag und Zahlungsmittel.',
            'empty_title' => 'Noch keine Abrechnung',
            'empty_description' => 'Sobald wir deinen offenen Betrag einziehen, steht der Vorgang hier – mit Beleg zum Herunterladen.',
            'date' => 'Datum',
            'amount' => 'Betrag',
            'method' => 'Zahlungsmittel',
            'method_unknown' => 'Nicht mehr hinterlegt',
            'status' => 'Status',
            'invoice' => 'Beleg',
            'status_labels' => [
                'pending' => 'Ausstehend',
                'processing' => 'In Bearbeitung',
                'retry_pending' => 'Erneuter Versuch am :date',
                'retry_pending_short' => 'Erneuter Versuch',
                'paid' => 'Bezahlt',
                'failed' => 'Fehlgeschlagen',
                'returned' => 'Zurückgebucht',
            ],
        ],
    ],
];
