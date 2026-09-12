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
        'marketplace' => 'Marktplatz',
        'buying_criteria' => 'Kaufkriterien',
        'caller_id' => 'Rufnummer',
        'balance' => 'Guthaben',
        'orders' => 'Bestellungen',
        'payments' => 'Zahlungen',
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
