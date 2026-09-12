<?php

/*
|--------------------------------------------------------------------------
| Lead-Marktplatz (FB-E5)
|--------------------------------------------------------------------------
|
| Eigene Sprachdatei je Thema statt eines weiteren Blocks in funnel.php: zwei
| Tickets, die gleichzeitig an derselben Sammeldatei anhaengen, kollidieren
| systematisch -- eine Datei je Zustaendigkeit tut das nie.
|
| Angesprochen als __('marketplace.<schluessel>'). Die Marktplatz-Tickets
| FB-051 bis FB-060 ergaenzen hier ihre eigenen Bereiche neben 'buyer'.
|
*/

return [

    'buyer' => [

        'status' => [
            'pending' => 'In Prüfung',
            'active' => 'Freigeschaltet',
            'rejected' => 'Abgelehnt',
        ],

        'not_approved' => 'Dieser Workspace ist noch nicht für den Marktplatz freigeschaltet.',

        'form' => [
            'heading' => 'Als Käufer registrieren',
            'description' => 'Nach dem Absenden prüfen wir die Angaben und schalten den Zugang zum Lead-Marktplatz frei.',
            'company_name' => 'Firma',
            'contact_name' => 'Ansprechpartner',
            'contact_email' => 'E-Mail des Ansprechpartners',
            'contact_phone' => 'Telefon (optional)',
            'broker_register_number' => 'Vermittlerregister-Nr. (optional)',
            'broker_register_number_helper' => 'Registrierungsnummer nach Paragraf 34d GewO, falls vorhanden. Käufer ohne Vermittlerzulassung lassen das Feld leer.',
            'vat_id' => 'Umsatzsteuer-Identifikationsnummer',
            'av_accepted' => 'Ich bestätige den Auftragsverarbeitungsvertrag.',
            'av_accepted_helper' => 'Ohne diese Bestätigung dürfen wir keine personenbezogenen Lead-Daten an dich übermitteln.',
            'submit' => 'Registrierung absenden',
            'already_registered' => 'Für dieses Konto liegt bereits eine Registrierung vor.',
            'received_heading' => 'Registrierung eingegangen',
            'received_text' => 'Wir haben die Registrierung für ":company" erhalten.',
            'received_hint' => 'Sobald der Zugang freigeschaltet ist, erreichst du den Lead-Marktplatz. Bis dahin sind keine Leads sichtbar.',
        ],

        'validation' => [
            'required' => 'Bitte fülle das Feld ":attribute" aus.',
            'email' => 'Bitte gib unter ":attribute" eine gültige E-Mail-Adresse an.',
            'max' => 'Das Feld ":attribute" ist zu lang.',
            'vat_id' => 'Die Umsatzsteuer-Identifikationsnummer beginnt mit dem Ländercode, z. B. DE123456789.',
            'av_accepted' => 'Ohne die Bestätigung des Auftragsverarbeitungsvertrags können wir den Zugang nicht anlegen.',
        ],

        'resource' => [
            'label' => 'Käufer-Registrierung',
            'plural_label' => 'Käufer-Registrierungen',
            'empty_heading' => 'Noch keine Registrierungen',
            'empty_description' => 'Registrierungen erscheinen hier, sobald sich ein Käufer angemeldet hat.',
            'read_only' => 'Die Angaben stammen vom Käufer und werden nicht bearbeitet. Möglich sind Freischalten und Ablehnen.',
        ],

        'fields' => [
            'company_name' => 'Firma',
            'status' => 'Status',
            'contact_name' => 'Ansprechpartner',
            'contact_email' => 'E-Mail',
            'contact_phone' => 'Telefon',
            'vat_id' => 'USt-IdNr.',
            'broker_register_number' => 'Vermittlerregister-Nr.',
            'av_accepted_at' => 'AV-Vertrag bestätigt am',
            'tenant' => 'Workspace',
            'created_at' => 'Eingegangen am',
            'reviewed_by' => 'Entschieden von',
            'reviewed_at' => 'Entschieden am',
            'rejection_reason' => 'Begründung der Ablehnung',
        ],

        'hints' => [
            'broker_register_number' => 'Optional: nicht jeder Käufer ist Versicherungsvermittler. Im Zweifel im Einzelfall prüfen.',
            'rejection_reason' => 'Mindestens 10 Zeichen. Die Begründung wird dem Käufer angezeigt.',
        ],

        'actions' => [
            'approve' => 'Freischalten',
            'approve_confirm' => 'Der Käufer erhält damit Zugang zum Lead-Marktplatz.',
            'approved' => 'Käufer wurde freigeschaltet.',
            'reject' => 'Ablehnen',
            'rejected' => 'Käufer wurde abgelehnt.',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Kaufkriterien (FB-051)
    |--------------------------------------------------------------------------
    */

    'profile' => [

        'heading' => 'Kaufkriterien',
        'nav_label' => 'Kaufkriterien',
        'description' => 'Lege fest, welche Leads du sehen willst. Ein leeres Feld schränkt nicht ein - ohne Angabe siehst du alles.',
        'saved' => 'Kaufkriterien wurden gespeichert.',
        'submit' => 'Speichern',

        // Portalfassung (Portal Phase 1): dieselben Kriterien, in fuenf Fragen
        // sortiert. Die Texte der Filament-Fassung bleiben daneben stehen.
        'portal' => [
            'heading' => 'Kaufkriterien',
            'description' => 'Lege fest, welche Leads du sehen willst. Ein leeres Feld schränkt nicht ein – ohne Angabe siehst du alles.',

            'source' => [
                'title' => 'Woher',
                'subtitle' => 'Aus welchen Fragebogen sollen die Leads kommen?',
                'label' => 'Fragebogen',
                'placeholder' => 'Weitere wählen …',
                'hint' => 'Ohne Auswahl siehst du Leads aus allen Fragebogen.',
                'remove' => ':name entfernen',
            ],

            'region' => [
                'title' => 'Wo',
                'subtitle' => 'In welchen Regionen suchst du Kunden?',
                'label' => 'Postleitzahl-Bereiche',
                'placeholder' => 'PLZ-Anfang, Enter',
                'hint' => '„76" deckt 76001 bis 76999 ab. Leer: ganz Deutschland.',
                'radius' => 'Umkreis um deinen Standort statt PLZ',
                'radius_action' => 'Standort setzen',
                'too_many' => 'Mehr als :max Bereiche sind nicht möglich.',
                'bad_format' => 'Ein Bereich besteht aus bis zu :max Ziffern.',
            ],

            'answers' => [
                'title' => 'Was',
                'subtitle' => 'Welche Antworten muss ein Lead haben?',
                'label' => 'Antwortfilter',
                'active' => ':count aktiv',
                'field' => 'Merkmal',
                'accepted' => 'Akzeptierte Antworten',
                'one_is_enough' => '· eine reicht',
                'add_value' => 'Antwort',
                'add_filter' => 'Filter hinzufügen',
                'remove_filter' => 'Filter entfernen',
                'remove_value' => ':value entfernen',
                'rule' => 'Alle Filter müssen zutreffen. Innerhalb eines Filters reicht eine Antwort.',
                'no_fields' => 'Für die gewählten Fragebogen sind keine Merkmale hinterlegt.',
                'pick_value' => 'Antwort wählen',
            ],

            'min_score' => 'Mindestpunktzahl',
            'min_score_placeholder' => 'keine',
            'min_score_hint' => 'Leads ohne Punktzahl fallen raus, sobald hier ein Wert steht.',
            'max_price' => 'Preis bis',
            'max_price_placeholder' => 'egal',
            'max_price_hint' => 'Leads über diesem Preis siehst du nicht.',

            'auto' => [
                'title' => 'Automatisch kaufen',
                'subtitle' => 'Sollen wir passende Leads für dich kaufen?',
                'label' => 'Passende Leads automatisch kaufen',
                'explainer' => 'Aus: Du siehst passende Leads im Marktplatz und entscheidest selbst. An: Wir kaufen sofort, sobald ein Lead allen Kriterien entspricht.',
                'daily_limit' => 'Tageslimit',
                'daily_limit_hint' => 'Höchstzahl automatischer Käufe je Tag. 0: unbegrenzt.',
                'budget' => 'Wochenbudget',
                'budget_placeholder' => 'kein Limit',
                'budget_hint' => 'Reservierungen zählen mit.',
                'warning' => 'Automatische Käufe stoppen, sobald das Guthaben unter den Leadpreis fällt. Du bekommst dann eine E-Mail.',
            ],

            'notify' => [
                'title' => 'Benachrichtigung',
                'subtitle' => 'Wie erfährst du von neuen passenden Leads?',
                'label' => 'Benachrichtigung an',
                'hint' => 'Ohne Angabe verwenden wir die Adresse aus deiner Registrierung.',
                'when' => 'Wann',
                'immediate' => 'Sofort',
                'daily' => 'Täglich um 8 Uhr',
                'none' => 'Keine E-Mail',
            ],

            'dirty' => 'Ungespeicherte Änderungen',
            'clean' => 'Alles gespeichert',
            'discard' => 'Verwerfen',
            'save' => 'Speichern',

            'match' => [
                'heading' => 'Passt gerade auf',
                'count' => '{0} Kein Lead|{1} 1 Lead|[2,*] :count Leads',
                'context' => 'im Marktplatz · :count in den letzten 7 Tagen',
                'funnels' => 'Fragebogen :names',
                'all_funnels' => 'Alle Fragebogen',
                'prefixes' => 'PLZ :list',
                'postcodes' => 'PLZ',
                'all_regions' => 'Ganz Deutschland',
                'filter' => ':field :values',
                'or' => ' oder ',
                'auto_on' => 'Autokauf an',
                'auto_off' => 'Autokauf aus',
                'view' => 'Diese Leads ansehen',
            ],

            'tip_heading' => 'Tipp',
            'tip' => 'Zu enge Kriterien sind der häufigste Grund für einen leeren Marktplatz. Fang breit an und verfeinere, sobald du siehst, welche Leads sich lohnen.',
        ],

        'funnels' => 'Fragebogen',
        'funnels_helper' => 'Ohne Auswahl siehst du Leads aus allen Fragebogen.',
        'no_funnels' => 'Zurzeit ist kein Fragebogen veröffentlicht.',

        'postal_prefixes' => 'Regionen',
        'postal_prefixes_helper' => 'Anfänge von Postleitzahlen, mit Komma getrennt. "76" deckt alles von 76001 bis 76999 ab.',

        'answer_filters' => 'Antwortfilter',
        'answer_filters_helper' => 'Je Zeile ein Feldschlüssel und die Antworten, die du akzeptierst. Bei einer Mehrfachauswahl genügt eine Übereinstimmung.',
        'field_key_placeholder' => 'z. B. tierart',
        'values_placeholder' => 'z. B. hund, katze',
        'add_filter' => 'Filter hinzufügen',
        'remove_filter' => 'Entfernen',
        'no_answer_filters' => 'Noch kein Antwortfilter - du siehst Leads mit beliebigen Antworten.',

        'min_score' => 'Mindestpunktzahl',
        'min_score_helper' => 'Leer: keine Untergrenze. Leads ohne Punktzahl fallen raus, sobald hier ein Wert steht.',

        'daily_limit' => 'Tageslimit',
        'daily_limit_helper' => 'Höchstzahl automatischer Käufe je Tag. Leer oder 0: keine Begrenzung.',

        'auto_buy' => 'Passende Leads automatisch kaufen',
        'auto_buy_helper' => 'Ist der Autokauf aus, siehst du passende Leads im Marktplatz und entscheidest selbst.',

        'notify_email' => 'Benachrichtigung an',
        'notify_email_helper' => 'Ohne Angabe verwenden wir die Adresse aus deiner Registrierung.',

        'validation' => [
            'too_many_prefixes' => 'Es sind höchstens :max Regionen möglich.',
            'prefix_format' => '":prefix" ist keine Region: erlaubt sind Ziffern, höchstens :max Stellen.',
            'notify_email' => 'Bitte gib eine gültige E-Mail-Adresse an.',
            'min_score' => 'Die Mindestpunktzahl muss eine ganze Zahl sein.',
            'daily_limit' => 'Das Tageslimit muss eine ganze Zahl ab 0 sein.',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Marktplatz (FB-053)
    |--------------------------------------------------------------------------
    */

    'orders' => [
        'heading' => 'Bestellungen',
        'search' => 'Bestellung suchen',
        'search_placeholder' => 'Nummer oder Betrag',
        'year' => 'Jahr',
        'all_years' => 'Alle Jahre',
        'method_unknown' => 'Zahlungsweg unbekannt',
        'unknown_month' => 'Ohne Datum',
        'count_line' => '{0} Keine Bestellungen|[1,*] :count von :total Bestellungen',
        'load_more' => '{1} 1 weitere laden|[2,*] :count weitere laden',
        'footnote' => 'Jede Bestellung ist eine Guthaben-Aufladung. Was du für einzelne Leads bezahlt hast, siehst du im',
        'footnote_link' => 'Guthaben-Verlauf.',
        'stats' => [
            'year' => 'Dieses Jahr',
            'count' => 'Bestellungen',
            'count_short' => 'Anzahl',
            'pending' => 'Ausstehend',
            'pending_short' => 'Offen',
        ],
        'tabs' => [
            'label' => 'Status',
            'all' => 'Alle',
            'paid' => 'Bezahlt',
            'pending' => 'Ausstehend',
            'failed' => 'Fehlgeschlagen',
        ],
        'status' => [
            'paid' => 'Bezahlt',
            'pending' => 'Ausstehend',
            'failed' => 'Fehlgeschlagen',
            'refunded' => 'Erstattet',
            'disputed' => 'Strittig',
            'other' => 'Offen',
        ],
        'actions' => [
            'invoice' => 'Rechnung',
            'complete' => 'Zahlung abschließen',
            'complete_short' => 'Abschließen',
            'retry' => 'Erneut versuchen',
            'retry_short' => 'Erneut',
        ],
        'empty' => [
            'title' => 'Nichts gefunden',
            'text' => 'In diesem Status gibt es keine Bestellungen.',
        ],
    ],

    'listing' => [
        'fits_count' => '{0} Kein Lead passt|{1} 1 Lead passt|[2,*] :count Leads passen',
        'contact_after_purchase' => 'Kontakt nach dem Kauf',
        'purchase_short' => 'Kaufen',
        'purchase_now' => 'Jetzt kaufen',
        'load_more' => '{1} 1 weiteren laden|[2,*] :count weitere laden',
        'auto_top' => 'Neue Leads erscheinen automatisch oben.',
        'sheet_hint' => 'Name, Telefon und E-Mail siehst du nach dem Kauf. Berechnet wird erst bei erreichtem Anruf.',

        'result_count' => '{0} Kein Lead passt zu deinen Kriterien|{1} 1 Lead passt zu deinen Kriterien|[2,*] :count Leads passen zu deinen Kriterien',
        'empty_hint' => 'Neue Anfragen erscheinen hier automatisch. Erweitere deine Kaufkriterien, um mehr Leads zu sehen.',
        'no_funds' => 'Dein Guthaben ist aufgebraucht. Lade auf, um Leads zu kaufen.',
        'price_exceeds_balance' => 'Dein verfügbares Guthaben reicht für diesen Lead nicht.',
        'badge_new' => 'Neu',
        'locked_contact' => 'Telefon und E-Mail nach dem Kauf',
        'price' => 'Preis: :amount',
        'yesterday' => 'gestern, :time',
        'gone' => 'Der Lead wurde gerade von jemand anderem gekauft.',
        'show_less' => 'Weniger anzeigen',
        'show_more' => '{1} +1 weiteres Merkmal|[2,*] +:count weitere Merkmale',

        'sort' => [
            'label' => 'Sortierung',
            'newest' => 'Neueste zuerst',
            'oldest' => 'Älteste zuerst',
            'score' => 'Höchste Punktzahl zuerst',
        ],

        // Portal Phase 1: Die Filterleiste des Marktplatzes. Die Auswahl
        // stammt aus den eigenen Treffern, deshalb heisst die Region nur
        // "76…" und nicht die vollstaendige Postleitzahl.
        'filters' => [
            'remove_chip' => 'Filter :filter entfernen',
            'industry' => 'Branche',
            'region' => 'Region',
            'max_price' => 'Preis bis',
            'all' => 'Alle',
            'reset' => 'Filter zurücksetzen',
            'region_option' => ':group…',
            'price_option' => 'bis :amount',
        ],

        'heading' => 'Marktplatz',
        'nav_label' => 'Marktplatz',
        'description' => 'Verfügbare Leads, die deinen Kaufkriterien entsprechen. Kontaktdaten werden erst nach dem Kauf sichtbar.',
        'no_profile' => 'Du hast noch keine Kaufkriterien hinterlegt - dir werden deshalb alle verfügbaren Leads angezeigt.',
        'empty' => 'Zurzeit passt kein verfügbarer Lead zu deinen Kaufkriterien.',

        'only_watchlisted' => 'Nur Merkliste',

        'score' => ':score Punkte',
        'region' => 'Region',
        'email' => 'E-Mail',
        'phone' => 'Telefon',
        'result' => 'Ergebnis',
        'unknown_funnel' => 'Unbekannter Fragebogen',
        'taken' => 'Vergriffen',
        'masked_hint' => 'Kontaktdaten sind bis zum Kauf verdeckt.',

        'watch' => 'Merken',
        'unwatch' => 'Nicht mehr merken',

        'purchase' => 'Lead kaufen',
        'purchase_unavailable' => 'Der Kauf ist noch nicht freigeschaltet.',

    ],

    /*
    |--------------------------------------------------------------------------
    | Kaufvorgang (FB-054)
    |--------------------------------------------------------------------------
    */

    'purchase' => [

        'balance' => 'Verfügbares Guthaben: :amount',
        'confirm' => 'Diesen Lead jetzt kaufen? Der Kaufpreis wird von deinem Guthaben reserviert und die Kontaktdaten werden freigegeben.',
        'done' => 'Lead gekauft. Die Kontaktdaten sind jetzt sichtbar, die Bestätigung ist unterwegs.',

        'errors' => [
            'already_taken' => 'Dieser Lead ist inzwischen vergeben. Ein anderer Käufer war schneller.',
            'buyer_not_approved' => 'Dieser Workspace ist noch nicht für den Marktplatz freigeschaltet.',
            'already_bought' => 'Diesen Lead hast du bereits gekauft.',
            'own_lead' => 'Dieser Lead stammt aus einem eigenen Fragebogen und kann nicht gekauft werden.',
            'price_changed' => 'Der Preis hat sich geändert, bitte prüfen Sie den Lead erneut.',
        ],

        'mail' => [
            'subject' => 'Dein gekaufter Lead',
            'label' => 'Leadkauf',
            'funnel_label' => 'Fragebogen',
            'heading' => 'Lead gekauft',
            'intro' => 'Du hast einen Lead aus dem Fragebogen ":funnel" gekauft. Die Kontaktdaten stehen unten.',
            'contact_heading' => 'Kontaktdaten',
            'phone_note' => 'Die Rufnummer steht aus Datenschutzgründen nicht in dieser E-Mail. Du erreichst den Lead über die Schaltfläche "Anrufen" im Portal; freigegeben wird die Nummer, sobald der Lead abgerechnet ist.',
            'cta' => 'Lead im Portal öffnen',
            'outro' => 'Melde dich zeitnah -- die Abschlussquote fällt mit jedem Tag, der vergeht.',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Verkaufsart (FB-055)
    |--------------------------------------------------------------------------
    */

    'sale_mode' => [
        'exclusive' => 'Exklusiv',
        'shared' => 'Mehrfachverkauf',
        'buyers' => ':buyers von :max Käufern',
    ],

    /*
    |--------------------------------------------------------------------------
    | Meine Leads (FB-057)
    |--------------------------------------------------------------------------
    */

    'purchased' => [

        'result_count' => '{0} Keine gekauften Leads|{1} 1 gekaufter Lead|[2,*] :count gekaufte Leads',

        'sort' => [
            'deadline' => 'Frist zuerst',
            'newest' => 'Zuletzt gekauft zuerst',
            'oldest' => 'Zuerst gekauft zuerst',
        ],
        'to_marketplace' => 'Zum Marktplatz',
        'search' => 'Lead suchen',
        'search_placeholder' => 'Name oder Ort suchen',
        'bought_today' => 'gekauft heute, :time',
        'bought_yesterday' => 'gekauft gestern, :time',
        'bought_on' => 'gekauft :date',
        'subtitle' => 'Sortiert nach dem, was als Nächstes ansteht.',
        'masking_hint' => 'Rufnummern werden nach dem ersten Gespräch sichtbar. Bis dahin rufst du über „Anrufen“ an.',
        'count_line' => '{1} 1 von :total Leads|[2,*] :count von :total Leads',
        'show_older' => 'Ältere anzeigen',
        'open_row' => ':name öffnen',
        'attempts_label' => ':done von :total Versuchen',
        'call' => 'Anrufen',
        'number' => 'Nummer',
        'details' => 'Details',
        'groups' => [
            'today' => [
                'title' => 'Heute fällig',
                'hint' => '{1} 1 Lead · sonst wird er berechnet|[2,*] :count Leads · sonst werden sie berechnet',
            ],
            'week' => [
                'title' => 'Diese Woche',
                'hint' => '{1} 1 Lead · Frist läuft|[2,*] :count Leads · Frist läuft',
            ],
            'done' => [
                'title' => 'Abgeschlossen',
                'hint' => '{1} 1 Lead|[2,*] :count Leads',
            ],
        ],
        'settlement' => [
            'billed' => ':amount berechnet',
            'released' => 'nicht berechnet',
        ],
        'tabs' => [
            'label' => 'Status',
            'all' => 'Alle',
            'open' => 'Offen',
            'reached' => 'Erreicht',
            'unreached' => 'Nicht erreicht',
        ],
        'badge' => [
            'today_at' => 'heute :time',
            'days_left' => '{1} noch 1 Tag|[2,*] noch :days Tage',
            'reached_on' => 'Erreicht :date',
            'unreached_on' => 'Nicht erreicht · :date',
            'deadline_running' => 'Frist läuft',
            'deadline_today' => 'Frist endet heute',
            'reached' => 'Erreicht',
            'unreached' => 'Nicht erreicht',
        ],
        'deadline_notice' => [
            'count' => '{1} 1 Frist endet heute.|[2,*] :count Fristen enden heute.',
            'text' => 'Bei :name fehlt noch ein Anrufversuch – sonst wird der Lead berechnet.',
        ],
        'line' => [
            'open' => ':count von :required Versuchen · :next · :deadline',
            'billable' => 'Gespräch :duration min am :date · :amount abgerechnet',
            'billable_no_talk' => 'Frist abgelaufen · :amount abgerechnet',
            'unreachable' => ':count von :required Versuchen · nicht berechnet · :amount freigegeben',
            'next_now' => 'nächster Versuch jetzt möglich',
            'next_at' => 'nächster Versuch ab :time',
            'deadline_today' => 'Frist endet heute, :time',
            'deadline_days' => 'Frist endet in :days Tagen',
            'deadline_over' => 'Frist abgelaufen',
            'deadline_none' => 'keine Frist gesetzt',
        ],
        'empty_state' => [
            'all' => [
                'title' => 'Hier ist noch nichts',
                'text' => 'Sobald du einen Lead kaufst, taucht er hier auf.',
            ],
            'open' => [
                'title' => 'Keine offenen Fristen',
                'text' => 'Alle gekauften Leads sind abgeschlossen.',
            ],
            'reached' => [
                'title' => 'Noch niemand erreicht',
                'text' => 'Sobald ein Gespräch lange genug dauert, landet der Lead hier.',
            ],
            'unreached' => [
                'title' => 'Alle erreicht',
                'text' => 'Kein Lead musste bisher freigegeben werden.',
            ],
            'search' => [
                'title' => 'Nichts gefunden',
                'text' => 'Zu dieser Suche passt kein gekaufter Lead. Versuche einen anderen Namen oder eine andere Postleitzahl.',
            ],
        ],
        'detail' => [
            'subheading' => 'Aus :funnel, gekauft am :date',
            'facts' => 'Zum Kauf',
            'back' => 'Zurück zu meinen Leads',
            'open' => 'Öffnen',
            'no_answers' => 'Zu diesem Lead wurden keine weiteren Angaben übermittelt.',
            'price' => 'Kaufpreis',

            // Was mit dem Geld gerade ist. Der Kaeufer liest hier, warum ein
            // Betrag noch reserviert ist -- oder dass er ihn zurueckhat.
            'price_status' => [
                'reserved' => 'Reserviert. Abgebucht wird erst nach bestätigter Erreichbarkeit.',
                'captured' => 'Abgebucht.',
                'released' => 'Freigegeben. Es wurde nichts abgebucht.',
                'refunded' => 'Erstattet.',
            ],
            'feedback_saved' => 'Rückmeldung gespeichert.',
        ],

        'heading' => 'Meine Leads',
        'nav_label' => 'Meine Leads',
        'description' => 'Die Leads, die du gekauft hast. Die Kontaktdaten stehen im Klartext.',
        'empty' => 'Du hast noch keinen Lead gekauft.',
        'only_without_feedback' => 'Nur ohne Rückmeldung',
        'export' => 'Als CSV herunterladen',
        'bought_at' => 'Gekauft am :date',
        'feedback_given' => 'Deine Rückmeldung: :feedback',

        // Portal-Detailseite (Portal Phase 1). Die Texte der Filament-Fassung
        // bleiben daneben stehen, bis das Portal abgenommen ist.
        'status' => [
            'label' => 'Status',
            'hint' => 'Nur für deine Übersicht. Hat keinen Einfluss auf die Abrechnung.',
            'open' => 'Offen',
            'appointment' => 'Termin vereinbart',
            'offer_sent' => 'Angebot geschickt',
            'no_demand' => 'Kein Bedarf',
        ],

        'notes' => [
            'heading' => 'Deine Notizen',
            'placeholder' => 'Was war beim letzten Kontakt? Was ist als Nächstes zu tun?',
            'hint' => 'Nur du und dein Workspace sehen diese Notizen.',
            'saving' => 'Wird gespeichert …',
            'saved' => 'Gespeichert',
        ],

        'portal' => [
            'back' => 'Meine Leads',
            'contact' => 'Kontakt',
            'request' => 'Anfrage',
            'request_received' => 'Eingegangen am :date über :funnel',
            'no_request_text' => 'Zu dieser Anfrage wurde kein Freitext übermittelt.',
            'attributes' => 'Merkmale',
            'no_attributes' => 'Zu diesem Lead wurden keine weiteren Angaben übermittelt.',
            'email_action' => 'E-Mail schreiben',
            'email_hint' => 'E-Mails zählen nicht als Erreichbarkeit. Für die Abrechnung zählt nur das Telefongespräch.',
            'phone_hint_masked' => 'Bis zur Abrechnung verdeckt. Du rufst über „Jetzt anrufen“ an, der Lead sieht dabei unsere Portalnummer.',
            'meta' => 'Aus :funnel · gekauft am :date',

            'deadline' => [
                'heading' => 'Frist und Abrechnung',
                'ends' => 'Endet am :date, :time',
                'ended' => 'Frist abgelaufen',
                'none' => 'Keine Frist hinterlegt',
                'today' => 'Frist endet heute',
                'today_word' => 'heute',
                'attempts' => 'Versuche',
                'attempts_value' => ':done von :total',
                'next' => 'Nächster Versuch',
                'next_now' => 'jetzt möglich',
                'next_at' => 'ab :time',
                'price' => 'Preis',
                'price_reserved' => ':amount reserviert',
                'consequence' => 'Noch :count Versuch ohne Gespräch, dann gilt der Lead als nicht erreichbar und :amount werden freigegeben. Läuft die Frist ohne den letzten Versuch ab, wird der Lead berechnet.|Noch :count Versuche ohne Gespräch, dann gilt der Lead als nicht erreichbar und :amount werden freigegeben. Läuft die Frist ohne den letzten Versuch ab, wird der Lead berechnet.',
                'settled' => 'Für diesen Lead ist bereits entschieden, was mit dem Geld passiert.',
                'call' => 'Jetzt anrufen',
            ],

            'calls' => [
                'heading' => 'Anrufe',
                'when_today' => 'heute, :time',
                'when_yesterday' => 'gestern, :time',
                'when_on' => ':date, :time',
                'duration' => ':duration min',
                'how_billing_works' => 'Wie die Abrechnung funktioniert',

                'empty' => 'Noch kein Versuch.',
                'not_counted' => 'zählt nicht: :reason',
            ],

            'facts' => [
                'heading' => 'Kaufdetails',
                'purchased_at' => 'Gekauft',
                'lead_number' => 'Lead-Nr.',
                'source' => 'Quelle',
                'workspace' => 'Workspace',
                'complaint' => 'Problem mit diesem Lead melden',
            ],
        ],

        'feedback' => [
            'interested' => 'Brauchbar',
            'not_interested' => 'Nicht brauchbar',
        ],

        'csv' => [
            'purchased_at' => 'Gekauft am',
            'funnel' => 'Fragebogen',
            'name' => 'Name',
            'email' => 'E-Mail',
            'phone' => 'Telefon',
            'postal_code' => 'PLZ',
            'score' => 'Punktzahl',
            'feedback' => 'Rückmeldung',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Reklamation (FB-058)
    |--------------------------------------------------------------------------
    */

    'complaint' => [

        'refund_reason' => 'Anerkannte Reklamation.',

        'mail' => [
            'subject' => 'Reklamation zu Lead #:lead',
            'label' => 'Reklamation',
            'heading' => 'Ein Käufer hat einen Lead reklamiert',
            'intro' => 'Ein Käufer beantragt, dass dieser Lead in einen anderen Zustand wechselt. Wird der Antrag anerkannt, bekommt er sein Guthaben zurück.',
            'filed_at' => 'Eingereicht am :date um :time Uhr',
            'facts_heading' => 'Zum Antrag',
            'lead' => 'Lead',
            'buyer' => 'Käufer',
            'cta' => 'Reklamation prüfen',
            'outro' => 'Über den Antrag entscheidet ein Mensch. Bis dahin bleibt der Lead unverändert.',
        ],

        'open' => 'Lead reklamieren',
        'help' => 'Beantrage, dass dieser Lead als unerreichbar oder ungültig gilt. Ein Mitarbeiter prüft den Antrag; wird er anerkannt, bekommst du dein Guthaben zurück.',
        'reason_placeholder' => 'Was ist passiert? Zum Beispiel: dreimal an verschiedenen Tagen angerufen, niemand erreichbar.',
        'submit' => 'Antrag absenden',
        'filed' => 'Reklamation eingereicht (:state) - Stand: :status.',

        'status' => [
            'pending' => 'In Prüfung',
            'approved' => 'Anerkannt',
            'rejected' => 'Abgelehnt',
        ],

        'errors' => [
            'not_your_purchase' => 'Dieser Kauf gehört nicht zu deinem Workspace.',
            'unsupported_state' => 'Reklamieren lässt sich nur "unerreichbar" oder "ungültig".',
            'reason_required' => 'Bitte gib an, was passiert ist - ohne Begründung lässt sich der Antrag nicht prüfen.',
            'lead_already_settled' => 'Dieser Lead ist bereits abgeschlossen und kann nicht mehr reklamiert werden.',
            'deadline_elapsed' => 'Die Reklamationsfrist für diesen Lead ist abgelaufen.',
            'already_filed' => 'Für diesen Kauf liegt bereits eine Reklamation vor.',
            'already_decided' => 'Über diese Reklamation wurde bereits entschieden.',
        ],

        'resource' => [
            'label' => 'Reklamation',
            'plural_label' => 'Reklamationen',
            'empty_heading' => 'Keine Reklamationen',
            'empty_description' => 'Anträge erscheinen hier, sobald ein Käufer einen Lead reklamiert.',
            'read_only' => 'Der Antrag stammt vom Käufer und wird nicht bearbeitet. Möglich sind Anerkennen und Ablehnen.',
        ],

        'fields' => [
            'created_at' => 'Eingegangen am',
            'status' => 'Stand',
            'buyer' => 'Käufer',
            'requested_state' => 'Beantragt',
            'reason' => 'Begründung',
            'reviewed_by' => 'Entschieden von',
            'reviewed_at' => 'Entschieden am',
            'decision_note' => 'Vermerk zur Entscheidung',
        ],

        'hints' => [
            'decision_note' => 'Mindestens 10 Zeichen. Der Vermerk begründet die Ablehnung.',
        ],

        'actions' => [
            'approve' => 'Anerkennen',
            'approve_confirm' => 'Der Lead wechselt in den beantragten Zustand und der Käufer bekommt sein Guthaben zurück.',
            'approved' => 'Reklamation anerkannt, Guthaben zurückgebucht.',
            'reject' => 'Ablehnen',
            'rejected' => 'Reklamation abgelehnt.',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Abrechnung (FB-059)
    |--------------------------------------------------------------------------
    */

    'billing' => [

        'heading' => 'Abrechnung',
        'nav_label' => 'Abrechnung',
        'description' => 'Was ein Käufer in einem Monat bekommen hat und was daraus geworden ist. Bezahlt wird im Voraus aus dem Wallet - abgerechnet wird hier nichts, es wird Rechenschaft abgelegt.',
        'buyer' => 'Käufer',
        'month' => 'Monat',
        'no_buyer' => 'Es ist kein Käufer angelegt.',
        'export' => 'Als CSV herunterladen',

        'leads' => 'Leads nach Zustand',
        'money' => 'Wallet und Umsatz',
        'purchases' => 'Käufe insgesamt',
        'revenue' => 'Umsatz',
        'topped_up' => 'Wallet aufgeladen',
        'captured' => 'Abgebucht für Leads',
        'refunded' => 'Erstattet',
        'captured_expected' => 'Abgerechnete Käufe laut Kaufbelegen',
        'money_hint' => 'Abgebuchtes Geld und die Summe der im Zeitraum abgerechneten Kaufpreise müssen übereinstimmen. Weichen sie ab, sind Wallet und Kaufstrecke auseinandergelaufen.',

        'invoices' => 'Rechnungen',
        'invoices_hint' => 'Die Rechnungen zu den Wallet-Aufladungen dieses Monats. Ausgestellt hat sie SaaSykit beim Checkout.',
        'no_invoices' => 'In diesem Monat wurde das Wallet nicht aufgeladen.',

        'csv' => [
            'buyer' => 'Käufer',
            'month' => 'Monat',
            'state' => 'Zustand',
            'count' => 'Anzahl',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Kaeufersicht (FB-060)
    |--------------------------------------------------------------------------
    */

    'overview' => [

        'heading' => 'Käufer',
        'nav_label' => 'Käufer',
        'description' => 'Umsatz und Reklamationsquote je Käufer. Der Durchschnitt liegt bei :average Prozent; auffällig ist, wer mehr als :points Prozentpunkte darüber liegt.',
        'empty' => 'Es ist kein Käufer angelegt.',

        'buyer' => 'Käufer',
        'purchases' => 'Käufe',
        'revenue' => 'Umsatz',
        'complaints' => 'Anerkannte Reklamationen',
        'rate' => 'Quote',
        'deviation' => 'Abweichung in Punkten',
        'flagged' => 'Auffällig',

        'hint' => 'Auffällig heißt nicht schuldig: Eine hohe Quote kann bedeuten, dass ein Käufer schlechte Leads bekommen hat - oder dass er zu großzügig reklamiert. Die Übersicht sagt nur, wo hinzusehen sich lohnt.',

    ],

    /*
    |--------------------------------------------------------------------------
    | Wallet und Ledger (LP-WALLET)
    |--------------------------------------------------------------------------
    */

    'wallet' => [

        /*
        |----------------------------------------------------------------------
        | Beleg zu einer Postpaid-Abrechnung (LP-POSTPAID-015)
        |----------------------------------------------------------------------
        |
        | Eigener Zweig `settlement_invoice`, damit er sich mit keinem der
        | parallel entstehenden Postpaid-Zweige beisst.
        |
        */

        'settlement_invoice' => [

            'title' => 'Abrechnung',
            'status_paid' => 'Bezahlt',
            'link' => 'Beleg',
            'action' => 'Beleg :reference herunterladen',
            'period' => ':start bis :end',
            'period_until' => 'bis :end',
            'method_unknown' => 'Hinterlegtes Zahlungsmittel',

            'fields' => [
                'period' => 'Abrechnungszeitraum',
                'method' => 'Zahlungsmittel',
            ],

            'items' => [
                'lead' => 'Lead #:lead',
                'funnel' => 'Funnel: :funnel',
                'captured_at' => 'abgerechnet am :date',
                'split' => 'Leadpreis :price zzgl. Aufschlag :surcharge',
                'refund' => 'Erstattung',
                'carried_over' => 'Übertrag aus dem vorherigen Abrechnungszeitraum',
                'credited' => 'Verrechnetes Guthaben',
                'balance_hint' => 'Differenz zwischen den aufgeführten Positionen und dem eingezogenen Betrag',
            ],

            'notes' => [
                'paid' => 'Der Betrag wurde am :date über :method eingezogen. Es ist nichts weiter zu tun.',
                'vat' => 'Alle Beträge sind Bruttobeträge und enthalten :percent % Umsatzsteuer.',
            ],

        ],

        /*
        |----------------------------------------------------------------------
        | SEPA-Vorabankuendigung vor dem Postpaid-Einzug (LP-POSTPAID-014)
        |----------------------------------------------------------------------
        |
        | Bewusst unter `wallet.prenotification` und nicht unter
        | `wallet.postpaid`: An dieser Datei arbeiten mehrere Postpaid-Tickets
        | gleichzeitig, und ein zweiter Block mit demselben Schluessel gewinnt
        | still und ueberschreibt den ersten.
        */

        /*
        |----------------------------------------------------------------------
        | Meldungen zum Postpaid-Einzug (LP-POSTPAID-008)
        |----------------------------------------------------------------------
        |
        | Eigener Ast neben 'prenotification': Jene kuendigt an, diese melden
        | das Ergebnis. 'error' geht als einzige an den Betreiber und nicht an
        | den Kaeufer -- eine technische Stoerung ist kein Zahlungsverzug.
        |
        */

        'settlement_notice' => [

            'label' => 'Pay as you go',
            'amount_label' => 'Betrag',
            'date_label' => 'Eingegangen am',
            'method_label' => 'Zahlungsmittel',
            'method_unknown' => 'Nicht mehr hinterlegt',
            'invoice_label' => 'Beleg',
            'payment_method_cta' => 'Zahlungsmittel prüfen',

            'paid' => [
                'subject' => 'Zahlung über :amount ist eingegangen',
                'heading' => 'Deine Zahlung ist eingegangen',
                'intro' => 'Wir haben :amount von deinem hinterlegten Zahlungsmittel eingezogen. Dein offener Betrag ist damit ausgeglichen.',
                'invoice_cta' => 'Beleg ansehen',
                'outro' => 'Der Beleg steht dir im Portal unter den Abrechnungen dauerhaft zur Verfügung.',
            ],

            'failed_retry' => [
                'subject' => 'Einzug über :amount ist fehlgeschlagen',
                'heading' => 'Der Einzug ist fehlgeschlagen',
                'intro' => 'Wir konnten :amount nicht einziehen. Wir versuchen es am :date erneut – bitte prüfe bis dahin dein Zahlungsmittel.',
                'date_label' => 'Nächster Versuch',
                'outro' => 'Scheitert auch der zweite Versuch, wird Pay as you go vorübergehend gesperrt und es fallen Gebühren an.',
            ],

            'failed_final' => [
                'subject' => 'Einzug über :amount endgültig fehlgeschlagen',
                'heading' => 'Der Einzug ist endgültig fehlgeschlagen',
                'intro' => 'Auch der zweite Versuch, :amount einzuziehen, ist gescheitert. Pay as you go ist damit vorerst gesperrt; der offene Betrag von :amount bleibt bestehen.',
                'outro' => 'Hinterlege ein neues Zahlungsmittel und gleiche den offenen Betrag aus, dann schalten wir Pay as you go wieder frei.',
            ],

            'error' => [
                'subject' => 'Einzug #:settlement technisch gescheitert',
                'heading' => 'Einzug konnte nicht angestoßen werden',
                'intro' => 'Der Einzug ist nach drei Versuchen an einer technischen Störung gescheitert – nicht an einer Ablehnung des Zahlungsmittels. Der Käufer wurde deshalb nicht zurückgestuft und nicht benachrichtigt.',
                'settlement_label' => 'Abrechnung',
                'wallet_label' => 'Wallet',
                'reason_label' => 'Fehler',
                'outro' => 'Die Forderung bleibt bestehen und muss im Admin von Hand neu angestoßen werden.',
            ],

        ],

        /*
        |----------------------------------------------------------------------
        | Aufhebung der Kaufsperre (LP-POSTPAID-009)
        |----------------------------------------------------------------------
        |
        | Gegenstueck zur Rueckstufungsmail und bewusst kurz: kein Rueckblick
        | auf den Grund der Sperre.
        */
        'unblocked' => [

            'mail' => [
                'subject' => 'Dein Konto ist wieder freigeschaltet',
                'label' => 'Guthaben',
                'heading' => 'Dein Konto ist wieder freigeschaltet',
                'intro' => 'Dein offener Betrag ist ausgeglichen. Dein Guthaben beträgt :balance und du kannst ab sofort wieder Leads kaufen.',
                'cta' => 'Zum Marktplatz',
                'outro' => 'Pay as you go bleibt vorerst deaktiviert – du kannst es jederzeit neu beantragen.',
            ],

        ],

        'prenotification' => [

            'mail' => [
                'subject' => 'Ankündigung: Wir buchen am :date :amount ab',
                'label' => 'SEPA-Lastschrift',
                'heading' => 'Ankündigung deiner Abbuchung',
                'intro' => 'Wir ziehen :amount am :date per SEPA-Lastschrift von deinem Konto ein. Diese Ankündigung schicken wir dir vor jeder Abbuchung, damit du sie zuordnen kannst.',
                'amount_label' => 'Betrag',
                'date_label' => 'Belastungsdatum',
                'iban_label' => 'Konto',
                'mandate_label' => 'Mandatsreferenz',
                'mandate_unknown' => 'Wird mit der Abbuchung mitgeteilt',
                'creditor_label' => 'Zahlungsempfänger',
                'coverage_hint' => 'Bitte sorge bis dahin für Deckung auf dem Konto. Wird die Lastschrift zurückgegeben, fallen Gebühren an und dein Pay-as-you-go-Zugang wird vorübergehend gesperrt.',
                'support_hint' => 'Stimmt etwas nicht? Melde dich vor dem Belastungsdatum bei uns, dann klären wir das ohne Rücklastschrift.',
            ],

        ],

        /*
        |----------------------------------------------------------------------
        | Eignung, Antrag und Freischaltung von Pay as you go (LP-POSTPAID-006)
        |----------------------------------------------------------------------
        |
        | Die Begruendungen der Eignungspruefung stehen dem Kaeufer im Portal
        | vor Augen und sind deshalb als Saetze geschrieben, nicht als
        | Regelnamen. Die Ablehnungsmail nennt bewusst keinen Grund: Wer
        | erfaehrt, an welcher Zahl es lag, kann genau diese Zahl herstellen.
        */

        'postpaid' => [

            'eligibility' => [

                'reasons' => [
                    'no_tenant' => 'Zu diesem Guthabenkonto ist kein Workspace hinterlegt.',
                    'purchases' => 'Du hast bisher :count abgerechnete Leadkäufe, nötig sind :required.',
                    'account_age' => 'Dein Konto besteht seit :days Tagen, nötig sind :required.',
                    'payment_history' => 'In den letzten :days Tagen gab es eine Zahlungsstörung auf deinem Konto.',
                    'previous_downgrade' => 'Dein Pay-as-you-go-Zugang wurde innerhalb der letzten :days Tage wegen einer Zahlungsstörung beendet.',
                    'purchase_blocked' => 'Für dein Konto besteht zurzeit eine Kaufsperre.',
                ],

            ],

            'errors' => [
                'disabled' => 'Pay as you go steht zurzeit nicht zur Verfügung.',
                'not_eligible' => 'Du erfüllst die Voraussetzungen für Pay as you go noch nicht.',
                'payment_method_required' => 'Hinterlege zuerst ein Zahlungsmittel, von dem wir einziehen können.',
                'application_pending' => 'Dein Antrag liegt uns bereits vor. Wir melden uns, sobald er geprüft ist.',
                'rejected_recently' => 'Ein neuer Antrag ist in :days Tagen wieder möglich.',
                'already_enabled' => 'Du kaufst bereits mit Pay as you go.',
            ],

            'mail' => [

                'received' => [
                    'subject' => 'Pay as you go beantragt: :buyer',
                    'label' => 'Antrag',
                    'heading' => 'Neuer Antrag auf Pay as you go',
                    'intro' => ':buyer möchte Leads mit Pay as you go kaufen. Der Antrag wartet auf deine Entscheidung im Admin-Bereich.',
                    'buyer_label' => 'Käufer',
                    'applicant_label' => 'Antragsteller',
                    'purchases_label' => 'Abgerechnete Käufe',
                    'account_age_label' => 'Kontoalter',
                    'account_age_value' => ':days Tage',
                    'balance_label' => 'Guthaben',
                    'reference_label' => 'Antrag',
                    'hint' => 'Die vollständigen Prüfzahlen stehen am Antrag im Admin-Bereich.',
                ],

                'approved' => [
                    'subject' => 'Pay as you go ist freigeschaltet',
                    'label' => 'Freigeschaltet',
                    'heading' => 'Du kaufst ab jetzt mit Pay as you go',
                    'intro' => 'Wir haben deinen Antrag freigegeben. Du kaufst Leads ohne Vorauszahlung und wir ziehen den offenen Betrag von deinem hinterlegten Zahlungsmittel ein.',
                    'credit_limit_label' => 'Dein Kreditrahmen',
                    'surcharge_label' => 'Aufschlag je Lead',
                    'surcharge_value' => ':percent % des Leadpreises',
                    'settlement_label' => 'Abrechnung',
                    'settlement_value' => 'Wöchentlich, dazu sofort ab :threshold offen',
                    'prenotification_hint' => 'Vor jeder Lastschrift kündigen wir dir Betrag und Belastungsdatum per E-Mail an.',
                    'hint' => 'Sorge für Deckung auf dem hinterlegten Konto. Scheitert ein Einzug, sperren wir den Kauf und stufen dich auf Vorauszahlung zurück.',
                ],

                'rejected' => [
                    'subject' => 'Dein Antrag auf Pay as you go',
                    'label' => 'Antrag',
                    'heading' => 'Wir können Pay as you go noch nicht freischalten',
                    'intro' => 'Wir haben deinen Antrag geprüft und können ihn zurzeit nicht freigeben. Das ist keine Bewertung deines Unternehmens, sondern eine Frage unserer Vergaberegeln.',
                    'retry_hint' => 'Du kannst in :days Tagen erneut beantragen. Bis dahin kaufst du wie gewohnt mit Guthaben.',
                    'support_hint' => 'Fragen dazu? Melde dich bei uns.',
                ],

                'downgraded' => [
                    'subject' => 'Pay as you go ist beendet',
                    'operator_subject' => 'Pay as you go beendet: :buyer',
                    'label' => 'Pay as you go',
                    'heading' => 'Pay as you go ist beendet',
                    'operator_hint' => 'Kopie zur Kenntnis. Betroffen ist :buyer.',
                    'intro' => 'Wir haben deinen Pay-as-you-go-Zugang beendet. Du kaufst ab jetzt wieder mit Guthaben, das du vorher auflädst.',
                    'reason_label' => 'Grund',
                    'open_amount_label' => 'Offener Betrag',
                    'fee_label' => 'Gebühr',
                    'reasons' => [
                        'settlement_failed' => 'Der Einzug ist zweimal fehlgeschlagen',
                        'sepa_return' => 'Die Lastschrift wurde zurückgegeben',
                        'chargeback' => 'Die Kartenzahlung wurde angefochten',
                        'no_payment_method' => 'Beim Einzug war kein Zahlungsmittel hinterlegt',
                        'payment_method_revoked' => 'Dein Zahlungsmittel wurde widerrufen',
                    ],
                    'blocked_hint' => 'Solange der offene Betrag besteht, kannst du keine neuen Leads kaufen. Lade dein Guthaben um mindestens den offenen Betrag auf, dann ist dein Konto sofort wieder freigeschaltet.',
                    'open_hint' => 'Gleiche den offenen Betrag bitte per Aufladung aus. Deine gekauften Leads bleiben dir in jedem Fall erhalten.',
                    'deadline_hint' => 'Bitte gleiche den offenen Betrag innerhalb von :days Tagen aus.',
                    'cta' => 'Guthaben aufladen',
                    'outro' => 'Pay as you go kannst du später erneut beantragen. Melde dich bei Fragen einfach bei uns – wir finden eine Lösung.',
                ],

            ],

        ],

        /*
        |----------------------------------------------------------------------
        | Guthaben und Transaktionsverlauf im Kaeuferportal (LP-WALLET-011)
        |----------------------------------------------------------------------
        |
        | Eigener Zweig neben 'seller': Kaeufer und Verkaeufer lesen dieselben
        | Buchungsarten ('types'), aber nicht dieselben Erklaerungen.
        |
        */

        'buyer' => [

            'title' => 'Guthaben & Transaktionen',
            'nav_label' => 'Guthaben',
            'top_up_heading' => 'Guthaben aufladen',

            // Guthabenkopf im Portal. Gross steht immer das verfuegbare
            // Guthaben; der reservierte Teil wird erklaert, nicht nur genannt.
            'balance' => [
                'available_label' => 'Verfügbar',
                'reserved_hint' => 'davon reserviert: :amount',
                'reserved_tooltip' => 'Reservierte Beträge werden erst nach bestätigter Erreichbarkeit abgebucht.',
            ],

            'history' => [
                'heading' => 'Transaktionsverlauf',
                'date' => 'Datum',
                'description' => 'Beschreibung',
                'amount' => 'Betrag',
                'balance_after' => 'Saldo danach',
                'empty' => 'Für diesen Zeitraum gibt es keine Buchungen.',
                'lead_link' => 'Zu Lead #:lead',
                'reserved_note' => 'Eine Reservierung ändert den Saldo nicht: Sie blockt den Betrag, bis die Erreichbarkeit feststeht. Erst die Abbuchung mindert den Saldo.',
                'result_count' => '{0} Keine Buchung|{1} 1 Buchung|[2,*] :count Buchungen',

                'filter' => [
                    'type' => 'Art',
                    'period' => 'Zeitraum',
                    'all_types' => 'Alle Arten',
                    'all_time' => 'Gesamter Zeitraum',
                    'days' => 'Letzte :days Tage',
                ],
            ],

        ],

        /*
        |----------------------------------------------------------------------
        | Adminwerkzeuge zum Wallet (LP-WALLET-013)
        |----------------------------------------------------------------------
        */

        'admin' => [

            /*
            |------------------------------------------------------------------
            | Adminwerkzeuge zu Pay as you go (LP-POSTPAID-012)
            |------------------------------------------------------------------
            |
            | Antrags-Queue, Einzuege und die Handlungen am Wallet. Eigener
            | Zweig unter 'admin', weil der Kaeufer diese Texte nie zu sehen
            | bekommt -- was er liest, steht unter 'wallet.postpaid'.
            |
            */
            'postpaid' => [

                'yes' => 'Ja',
                'no' => 'Nein',
                'blocked_yes' => 'Gesperrt',
                'blocked_no' => 'Frei',
                'no_payment_method' => 'Kein Zahlungsmittel',
                'reenable_note' => 'Vom Betreiber nach einer Rückstufung wieder freigeschaltet.',

                'payment_mode' => [
                    'prepaid' => 'Vorauszahlung',
                    'postpaid' => 'Pay as you go',
                ],

                'fields' => [
                    'payment_mode' => 'Zahlungsmodus',
                    'credit_limit' => 'Kreditrahmen',
                    'credit_limit_cents' => 'Kreditrahmen in Cent',
                    'open_amount' => 'Offener Betrag',
                    'open_total' => 'Offene Forderungen gesamt',
                    'blocked' => 'Gesperrt',
                    'disabled_reason' => 'Grund der Rückstufung',
                    'downgrade_reason' => 'Grund',
                    'fee_cents' => 'Gebühr in Cent',
                ],

                'hints' => [
                    'credit_limit_cents' => 'Betrag, um den der Saldo ins Minus laufen darf. 30000 sind 300,00 €.',
                    'downgrade_reason' => 'Am Grund hängen Gebühr, Kaufsperre und der Text der E-Mail an den Käufer.',
                    'fee_cents' => 'Vorbelegt ist die Gebühr des gewählten Grundes. 0 erhebt ausdrücklich keine.',
                ],

                'actions' => [
                    'change_credit_limit' => 'Kreditrahmen ändern',
                    'change_credit_limit_description' => 'Der Rahmen ist eine Erlaubnis, kein Guthaben – es wird nichts gebucht. Ein gesenkter Rahmen stoppt weitere Käufe, der bereits offene Betrag bleibt bestehen.',
                    'credit_limit_changed' => 'Kreditrahmen geändert',
                    'settle_now' => 'Jetzt einziehen',
                    'settle_now_description' => 'Zieht den heute offenen Betrag ein, ohne den Wochentermin abzuwarten. Bei SEPA wird zuerst angekündigt und erst nach Ablauf der Frist belastet.',
                    'settled' => 'Einzug angestoßen',
                    'downgrade' => 'Auf Prepaid zurückstufen',
                    'downgrade_description' => 'Beendet Pay as you go: Kreditrahmen auf 0, offene Einzüge werden beendet, bei offenem Betrag folgt die Kaufsperre. Käufer und Betreiber bekommen eine E-Mail.',
                    'downgraded' => 'Auf Vorauszahlung zurückgestuft',
                    'reenable' => 'Postpaid wieder freischalten',
                    'reenable_description' => 'Schaltet Pay as you go trotz der früheren Rückstufung wieder frei. Die Eignungsprüfung wird dabei übergangen – das ist eine Entscheidung gegen die Regel. Der Käufer bekommt die Freischaltungsmail.',
                    'reenabled' => 'Pay as you go wieder freigeschaltet',
                ],

                'stats' => [
                    'open_receivables' => 'Offene Forderungen',
                    'open_receivables_hint' => '{0} Kein Käufer im Minus|{1} :count Käufer im Minus|[2,*] :count Käufer im Minus',
                    'in_flight' => 'Einzüge in Bearbeitung',
                    'in_flight_hint' => '{0} Kein Einzug unterwegs|{1} :count Einzug unterwegs|[2,*] :count Einzüge unterwegs',
                    'failed' => 'Fehlgeschlagene Einzüge',
                    'failed_hint' => 'Gescheitert oder zurückgegeben in den letzten :days Tagen',
                    'surcharge' => 'Ertrag aus Aufschlag',
                    'surcharge_hint' => 'Pay-as-you-go-Aufschlag im laufenden Monat',
                ],

                'application' => [

                    'resource' => [
                        'label' => 'Pay-as-you-go-Antrag',
                        'plural_label' => 'Pay-as-you-go-Anträge',
                    ],

                    'empty_heading' => 'Kein offener Antrag',
                    'empty_description' => 'Sobald ein Käufer Pay as you go beantragt, steht sein Antrag hier.',
                    'snapshot_hint' => 'Die Zahlen zum Zeitpunkt der Antragstellung. Sie werden nicht neu berechnet – sie belegen, worüber entschieden wird.',

                    'sections' => [
                        'application' => 'Antrag',
                        'eligibility' => 'Eignungsprüfung',
                        'context' => 'Zahlungsmittel und Kaufhistorie',
                    ],

                    'fields' => [
                        'requested_at' => 'Beantragt',
                        'buyer' => 'Käufer',
                        'workspace' => 'Workspace',
                        'status' => 'Stand',
                        'decided_at' => 'Entschieden',
                        'decided_by' => 'Entschieden von',
                        'note' => 'Notiz',
                        'rule' => 'Angabe',
                        'value' => 'Wert',
                        'payment_method' => 'Zahlungsmittel',
                        'captured_purchases' => 'Abgerechnete Käufe',
                        'purchase_history' => 'Kaufhistorie (tagesaktuell)',
                    ],

                    'hints' => [
                        'note_required' => 'Bleibt intern. Der Käufer erfährt nur, wann er wieder beantragen darf.',
                    ],

                    'status' => [
                        'requested' => 'Offen',
                        'approved' => 'Freigegeben',
                        'rejected' => 'Abgelehnt',
                    ],

                    'actions' => [
                        'approve' => 'Freigeben',
                        'approve_confirm' => 'Der Käufer kauft danach gegen Kreditrahmen und zahlt im Nachhinein. Er bekommt die Freischaltung per E-Mail.',
                        'approved' => 'Pay as you go freigegeben',
                        'reject' => 'Ablehnen',
                        'reject_confirm' => 'Der Käufer erfährt nur, dass wir nicht freischalten, und wann er erneut beantragen darf.',
                        'rejected' => 'Antrag abgelehnt',
                    ],

                    'snapshot' => [
                        'eligible' => 'Eignung',
                        'eligible_yes' => 'Alle Regeln erfüllt',
                        'eligible_no' => 'Nicht alle Regeln erfüllt',
                        'of_required' => ':value (nötig: :required)',
                        'captured_purchases' => 'Abgerechnete Käufe',
                        'account_age_days' => 'Kontoalter in Tagen',
                        'failed_settlements' => 'Fehlgeschlagene Einzüge im Beobachtungszeitraum',
                        'chargebacks' => 'Rückgaben im Beobachtungszeitraum',
                        'clean_history_days' => 'Beobachtungszeitraum in Tagen',
                        'balance_cents' => 'Saldo',
                        'open_amount_cents' => 'Offener Betrag',
                        'purchase_blocked' => 'Kaufsperre',
                        'postpaid_disabled_reason' => 'Frühere Rückstufung',
                        'reasons' => 'Offene Punkte',
                    ],

                    'history' => [
                        'captured' => 'Abgerechnete Käufe',
                        'released' => 'Aufgelöste Käufe',
                        'refunded' => 'Erstattete Käufe',
                        'revenue' => 'Umsatz der letzten 90 Tage',
                    ],

                ],

                'settlement' => [

                    'resource' => [
                        'label' => 'Einzug',
                        'plural_label' => 'Einzüge',
                    ],

                    'empty_heading' => 'Noch kein Einzug',
                    'empty_description' => 'Einzüge entstehen am Wochentermin, ab der Einzugsschwelle oder von Hand am Wallet.',

                    'fields' => [
                        'created_at' => 'Erstellt',
                        'buyer' => 'Käufer',
                        'amount' => 'Betrag',
                        'status' => 'Stand',
                        'attempts' => 'Versuche',
                        'next_attempt_at' => 'Nächster Versuch',
                        'payment_intent' => 'Zahlung beim Anbieter',
                        'failure_reason' => 'Fehlergrund',
                        'invoice_reference' => 'Beleg',
                    ],

                    'status' => [
                        'pending' => 'Offen',
                        'processing' => 'Unterwegs',
                        'retry_pending' => 'Zweiter Versuch',
                        'paid' => 'Bezahlt',
                        'failed' => 'Gescheitert',
                        'returned' => 'Zurückgegeben',
                    ],

                    'trigger' => [
                        'scheduled' => 'Wochentermin',
                        'threshold' => 'Einzugsschwelle',
                        'manual' => 'Von Hand',
                    ],

                    'actions' => [
                        'retry' => 'Erneut versuchen',
                        'retry_confirm' => 'Belastet das hinterlegte Zahlungsmittel erneut. Eingezogen wird der heute offene Betrag, nicht der Betrag des gescheiterten Versuchs. Bei SEPA wird vorher erneut angekündigt.',
                        'retried' => 'Einzug erneut angestoßen',
                    ],

                ],

            ],

            'resource' => [
                'label' => 'Wallet',
                'plural_label' => 'Wallets',
                'read_only' => 'Salden lassen sich hier nicht bearbeiten. Jede Änderung braucht eine Buchung als Beleg – dafür gibt es die Korrekturbuchung.',
            ],

            'platform_owner' => 'Plattform',

            'owner_type' => [
                'buyer' => 'Käufer',
                'seller' => 'Verkäufer',
                'platform' => 'Plattform',
            ],

            'type' => [
                'topup' => 'Aufladung',
                'reserve' => 'Reservierung',
                'capture' => 'Abbuchung',
                'release' => 'Auflösung',
                'refund' => 'Erstattung',
                'earning' => 'Einnahme',
                'commission' => 'Provision',
                'payout' => 'Auszahlung',
                'adjustment' => 'Korrektur',
                'opening_balance' => 'Eröffnungssaldo',
                'settlement' => 'Einzug',
                'surcharge' => 'Aufschlag',
                'fee' => 'Gebühr',
            ],

            'fields' => [
                'id' => 'Nr.',
                'owner' => 'Inhaber',
                'owner_type' => 'Typ',
                'balance' => 'Saldo',
                'reserved' => 'Reserviert',
                'available' => 'Verfügbar',
                'currency' => 'Währung',
                'sum' => 'Summe',
                'created_at' => 'Zeitpunkt',
                'type' => 'Art',
                'amount' => 'Betrag',
                'description' => 'Buchungstext',
                'balance_after' => 'Saldo danach',
                'reserved_after' => 'Reserviert danach',
                'idempotency_key' => 'Idempotenzschlüssel',
                'reference' => 'Beleg',
                'created_by' => 'Gebucht von',
                'meta' => 'Zusatzangaben',
                'amount_cents' => 'Betrag in Cent',
                'reason' => 'Begründung',
                'allow_negative' => 'Negativen Saldo zulassen',
            ],

            'hints' => [
                'amount_cents' => 'Positiv schreibt gut, negativ bucht ab. 1500 sind 15,00 €.',
                'reason' => 'Steht dauerhaft im Journal und ist der einzige Nachweis, warum diese Buchung entstanden ist.',
                'allow_negative' => 'Nur einschalten, wenn der Saldo bewusst ins Minus laufen soll. Sonst scheitert eine zu hohe Abbuchung – meist ein Tippfehler.',
            ],

            'actions' => [
                'adjust' => 'Korrekturbuchung',
                'adjust_description' => 'Bucht eine manuelle Korrektur ins Journal. Bestehende Buchungen bleiben unverändert; korrigiert wird durch Gegenbuchung.',
                'adjust_submit' => 'Korrektur buchen',
                'adjusted' => 'Korrektur gebucht',
            ],

            'descriptions' => [
                'adjustment' => 'Manuelle Korrektur: :reason',
            ],

            'ledger' => [
                'heading' => 'Journal',
                'description' => 'Jede Buchung dieses Wallets, neueste zuerst. Zeile aufklappen zeigt Beleg, Idempotenzschlüssel und Zusatzangaben.',
                'empty_heading' => 'Noch keine Buchung',
            ],

            'stats' => [
                'platform_balance' => 'Plattform-Wallet',
                'platform_balance_hint' => 'Kumulierte Provision abzüglich Erstattungen und Korrekturen',
                'commission_total' => 'Provision gesamt',
                'commission_total_hint' => 'Summe aller Provisionsbuchungen, Rückbuchungen eingerechnet',
                'open_payouts' => 'Offene Auszahlungen',
                'open_payouts_hint' => '{0} Keine offene Anforderung|{1} :count Anforderung wartet auf Überweisung|[2,*] :count Anforderungen warten auf Überweisung',
            ],

            'payout' => [

                'resource' => [
                    'label' => 'Auszahlung',
                    'plural_label' => 'Auszahlungen',
                ],

                'read_only' => 'Der Betrag hat das Verkäufer-Wallet bereits mit der Anforderung verlassen. Eine Ablehnung bucht ihn zurück.',
                'empty_heading' => 'Keine offene Auszahlung',
                'empty_description' => 'Sobald ein Verkäufer eine Auszahlung anfordert, steht sie hier.',
                'iban_changed' => 'Achtung: Die hinterlegte IBAN wurde nach der Anforderung geändert, angefordert wurde gegen •••• :last4.',

                'fields' => [
                    'requested_at' => 'Angefordert',
                    'seller' => 'Verkäufer',
                    'amount' => 'Betrag',
                    'iban' => 'IBAN',
                    'status' => 'Stand',
                    'processed_at' => 'Entschieden',
                    'processed_by' => 'Entschieden von',
                    'note' => 'Notiz',
                ],

                'hints' => [
                    'note_optional' => 'Freiwillig, etwa die Referenz der Überweisung.',
                    'note_required' => 'Der Verkäufer bekommt die Ablehnung per E-Mail; die Begründung gehört dazu.',
                ],

                'actions' => [
                    'mark_paid' => 'Überwiesen',
                    'mark_paid_confirm' => 'Nur bestätigen, wenn die Überweisung tatsächlich ausgelöst ist. Es wird nichts mehr gebucht – das Geld hat das Wallet mit der Anforderung verlassen.',
                    'marked_paid' => 'Als überwiesen vermerkt',
                    'reject' => 'Ablehnen',
                    'reject_confirm' => 'Der Betrag wird als Korrekturbuchung ins Verkäufer-Wallet zurückgebucht.',
                    'rejected' => 'Auszahlung abgelehnt',
                ],

            ],

            'purchase' => [

                'resource' => [
                    'label' => 'Leadkauf',
                    'plural_label' => 'Leadkäufe',
                ],

                'read_only' => 'Kaufbelege werden nicht bearbeitet. Die Geldseite ändert sich ausschließlich über eine Erstattung.',
                'empty_heading' => 'Noch kein Leadkauf',

                'status' => [
                    'reserved' => 'Reserviert',
                    'captured' => 'Abgerechnet',
                    'released' => 'Aufgelöst',
                    'refunded' => 'Erstattet',
                ],

                'fields' => [
                    'purchased_at' => 'Gekauft',
                    'lead' => 'Lead',
                    'buyer' => 'Käufer',
                    'seller' => 'Verkäufer',
                    'status' => 'Stand',
                    'price' => 'Preis',
                    'commission' => 'Provision',
                    'seller_net' => 'Erlös Verkäufer',
                    'captured_at' => 'Abgerechnet',
                    'released_at' => 'Aufgelöst',
                    'refunded_at' => 'Erstattet',
                    'reason' => 'Grund',
                ],

                'hints' => [
                    'reason' => 'Steht dauerhaft in allen Buchungen dieser Erstattung.',
                ],

                'actions' => [
                    'refund' => 'Lead erstatten',
                    'refund_confirm' => 'Der Kaufpreis geht an den Käufer zurück, Einnahme und Provision werden zurückgebucht – auch dann, wenn das Verkäufer-Wallet dadurch ins Minus läuft. Gibt es zu diesem Kauf eine offene Reklamation, bitte stattdessen dort entscheiden.',
                    'refunded' => 'Leadkauf erstattet',
                ],

            ],

        ],

        /*
        |----------------------------------------------------------------------
        | Hinterlegte Zahlungsmittel (LP-POSTPAID-005)
        |----------------------------------------------------------------------
        |
        | Der Mandatstext ist der Wortlaut des SEPA-Lastschriftmandats. Er steht
        | im Portal ueber der Bestaetigung, weil er Inhalt des Mandats ist: Ohne
        | ihn weiss der Kaeufer nicht, wem er eine Abbuchungserlaubnis erteilt.
        | Glaeubiger und Glaeubiger-ID werden eingesetzt, nicht ausgeschrieben --
        | sie stehen in config('wallet.postpaid.*').
        |
        */
        'payment_methods' => [
            'title' => 'Zahlungsmittel',
            'description' => 'Für Pay as you go brauchen wir einen Einzugsweg. Du kannst eine SEPA-Lastschrift oder eine Karte hinterlegen. Die Daten gibst du direkt bei unserem Zahlungsanbieter ein, wir sehen nur die letzten vier Stellen.',
            'choose' => 'Zahlungsmittel wählen',
            'add' => 'Zahlungsmittel hinterlegen',
            'added' => 'Zahlungsmittel hinterlegt.',
            'remove' => 'Entfernen',
            'removed' => 'Zahlungsmittel entfernt.',
            'default_badge' => 'Wird eingezogen',
            'empty' => 'Noch kein Zahlungsmittel hinterlegt.',

            'types' => [
                'sepa_debit' => 'SEPA-Lastschrift',
                'card' => 'Karte',
            ],

            'type_hints' => [
                'sepa_debit' => 'Abbuchung von deinem Konto. Du kannst eine Belastung bis zu acht Wochen danach zurückgeben lassen.',
                'card' => 'Kredit- oder Debitkarte. Die Zahlung wird sofort entschieden.',
            ],

            'status' => [
                'active' => 'Aktiv',
                'revoked' => 'Widerrufen',
                'failed' => 'Gescheitert',
            ],

            'mandate' => [
                'heading' => 'SEPA-Lastschriftmandat',
                'creditor_label' => 'Gläubiger',
                'creditor_id_label' => 'Gläubiger-Identifikationsnummer',
                'text' => 'Ich ermächtige :creditor (Gläubiger-Identifikationsnummer :creditor_id), Zahlungen von meinem Konto mittels SEPA-Lastschrift einzuziehen. Zugleich weise ich mein Kreditinstitut an, die von :creditor auf mein Konto gezogenen Lastschriften einzulösen. Ich kann innerhalb von acht Wochen, beginnend mit dem Belastungsdatum, die Erstattung des belasteten Betrages verlangen; es gelten dabei die mit meinem Kreditinstitut vereinbarten Bedingungen.',
                'accept' => 'Ich erteile das oben stehende SEPA-Lastschriftmandat.',
                'prenotification_hint' => 'Vor jeder Abbuchung kündigen wir dir Betrag und Belastungsdatum per E-Mail an.',
                'accepted_at' => 'Mandat erteilt am :date',
            ],

            'errors' => [
                'mandate_required' => 'Ohne erteiltes SEPA-Lastschriftmandat können wir nichts einziehen. Bitte bestätige das Mandat.',
                'mandate_missing' => 'Der Zahlungsanbieter hat uns kein Lastschriftmandat gemeldet. Bitte versuche es noch einmal.',
                'setup_not_completed' => 'Das Zahlungsmittel wurde nicht bestätigt (Stand: :status). Bitte versuche es noch einmal.',
                'setup_intent_unknown' => 'Diesen Vorgang kennen wir nicht. Bitte fange noch einmal an.',
                'last_method_postpaid' => 'Das ist dein einziges Zahlungsmittel und du kaufst mit Pay as you go. Hinterlege zuerst ein anderes, dann kannst du dieses entfernen.',
                'last_method_open_amount' => 'Das ist dein einziges Zahlungsmittel und es sind noch :amount offen. Hinterlege zuerst ein anderes oder begleiche den offenen Betrag.',
                'provider_unavailable' => 'Der Zahlungsanbieter ist gerade nicht erreichbar. Bitte versuche es in einigen Minuten noch einmal.',
            ],
        ],

        'top_up' => [
            'title' => 'Guthaben aufladen',
            'nav_label' => 'Guthaben',
            'balance_label' => 'Dein Guthaben',
            'reserved_label' => 'Davon reserviert',
            'available_label' => 'Verfügbar',
            'description' => 'Mit Guthaben kaufst du Leads im Marktplatz. Wähle einen Betrag, die Zahlung läuft über den gewohnten Checkout. Nach bestätigter Zahlung wird der Betrag sofort gutgeschrieben.',
            'amount_label' => 'Betrag',
            'amount_hint' => 'Mindestens :min €, höchstens :max €. Nur ganze Euro.',
            'submit' => 'Weiter zur Zahlung',
            'checkout_hint' => 'Mit deinem Guthaben kaufst du Leads im Marktplatz. Es verfällt nicht.',
            'payment_hint' => 'Die Zahlung wird über den Zahlungsanbieter des Portals abgewickelt. Rechnung und Zahlungsbeleg findest du unter „Bestellungen“.',

            'errors' => [
                'min' => 'Der kleinste Aufladebetrag ist :amount €.',
                'max' => 'Der größte Aufladebetrag ist :amount €.',
                'unavailable' => 'Das Aufladen ist zurzeit nicht möglich. Bitte wende dich an den Support.',
            ],

            'success' => [
                'amount_label' => 'Aufladung',
                'done_text' => ':amount sind gutgeschrieben und sofort verfügbar.',
                'balance_now' => 'Dein Guthaben jetzt',
                'covers' => '{0} reicht für keinen Lead|{1} reicht für 1 Lead|[2,*] reicht für :count Leads',
                'invoice' => 'Rechnung als PDF',
                'invoice_mail' => 'Die Rechnung geht auch an :email.',
                'pending' => [
                    'heading' => 'Einen Moment',
                    'text' => 'Deine Zahlung ist da. Wir schreiben das Guthaben gerade gut.',
                    'step_paid' => 'Zahlung bestätigt',
                    'step_credit' => 'Guthaben wird gutgeschrieben',
                    'step_invoice' => 'Rechnung wird erstellt',
                    'hint' => 'Das dauert normalerweise unter fünf Sekunden. Du kannst die Seite offen lassen.',
                ],
                'slow' => [
                    'heading' => 'Dauert etwas länger',
                    'text' => 'Die Zahlung ist bestätigt, die Gutschrift hängt gerade. Es wurde nichts doppelt abgebucht – wir schreiben das Guthaben automatisch gut, sobald es durch ist, und schicken dir eine E-Mail.',
                    'reference' => 'Zahlungs-Referenz',
                    'support' => 'Support schreiben',
                ],
                'heading' => 'Guthaben aufgeladen',
                'pending_heading' => 'Fast fertig',
                'text' => ':amount wurden deinem Guthaben hinzugefügt.',
                'pending_text' => 'Sobald die Zahlung bestätigt ist, schreiben wir den Betrag gut. Bei Überweisungen dauert das meist ein bis zwei Werktage.',
                'balance_label' => 'Dein Guthaben',
                'pending_addition' => '+:amount nach Zahlungseingang',
                'unknown_heading' => 'Diese Bestellung kennen wir nicht',
                'unknown_text' => 'Der Link ist vielleicht abgelaufen. Dein Guthaben findest du im Marktplatz.',
            ],

            'mail' => [
                'subject' => 'Guthaben aufgeladen: :amount',
                'label' => 'Guthaben',
                'heading' => 'Guthaben aufgeladen',
                'intro' => 'Deine Zahlung ist eingegangen. Wir haben :amount auf dein Guthabenkonto gebucht.',
                'amount_label' => 'Aufgeladen',
                'balance_label' => 'Neues Guthaben',
                'history_hint' => 'Alle Buchungen deines Guthabenkontos siehst du jederzeit im Transaktionsverlauf im Portal.',
                'receipt_hint' => 'Rechnung und Zahlungsbeleg zu dieser Aufladung stehen in der Bestellbestätigung, die wir dir ebenfalls geschickt haben.',
                'cta' => 'Zum Transaktionsverlauf',
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | Bestellbestaetigung als Zahlungsbeleg (LP-WALLET-019)
        |----------------------------------------------------------------------
        |
        | Diese Mail ist der Beleg ueber die Zahlung: Bestellnummer, Positionen,
        | Rabatt und Gesamtbetrag. Die Gutschrift selbst bestaetigt
        | `top_up.mail` mit Betrag und neuem Stand -- der Beleg wiederholt den
        | Kontostand deshalb nicht, sondern verweist darauf.
        |
        | Eine Aufladung wird ueber ein Einmalkauf-Produkt zu 1,00 EUR
        | abgerechnet, die Menge im Warenkorb ist der Betrag in Euro. Diese
        | Menge ist ein Umsetzungsdetail des Checkouts und gehoert nicht in eine
        | Kundenmail -- die Position nennt deshalb den Betrag.
        */

        'order_mail' => [
            'subject' => 'Deine Bestellung bei :app',
            'label' => 'Zahlungsbeleg',
            'heading' => 'Bestellung bestätigt',
            'intro' => 'Danke für deine Bestellung. Die Zahlung ist eingegangen.',
            'intro_topup' => 'Danke für deine Aufladung. Die Zahlung ist eingegangen und der Betrag ist deinem Guthabenkonto gutgeschrieben.',
            'order_number' => 'Bestellnummer',
            'date' => 'Bestellt am',
            'items_heading' => 'Positionen',
            'quantity' => 'Menge: :count',
            'topup_amount' => 'Gutschrift auf dein Guthabenkonto: :amount',
            'subtotal' => 'Zwischensumme',
            'discount' => 'Rabatt',
            'total' => 'Gesamt',
            'cta' => 'Zum Marktplatz',
            'topup_hint' => 'Deinen neuen Guthabenstand nennt dir die Bestätigung der Aufladung, die wir dir gerade geschickt haben.',
            'support' => 'Fragen zur Bestellung? Schreib uns an :email.',
            'outro' => 'Viele Grüße, dein :app-Team',
        ],

        /*
        |----------------------------------------------------------------------
        | Buchungsarten im Verlauf (LP-WALLET-012)
        |----------------------------------------------------------------------
        */

        'types' => [
            'topup' => 'Aufladung',
            'reserve' => 'Reserviert',
            'capture' => 'Abgebucht',
            'release' => 'Freigegeben',
            'refund' => 'Erstattung',
            'earning' => 'Einnahme',
            'commission' => 'Provision',
            'payout' => 'Auszahlung',
            'adjustment' => 'Korrektur',
            'opening_balance' => 'Eröffnungssaldo',
            'settlement' => 'Einzug',
            'surcharge' => 'Aufschlag',
            'fee' => 'Gebühr',
        ],

        /*
        |----------------------------------------------------------------------
        | Verkaeufer-Portal: Preis, Einnahmen, Auszahlung (LP-WALLET-012)
        |----------------------------------------------------------------------
        */

        'seller' => [

            'title' => 'Einnahmen',
            'nav_label' => 'Einnahmen',

            'price' => [
                'heading' => 'Preis pro Lead',
                'description' => 'Zu diesem Preis bieten wir deine Leads im Marktplatz an.',
                'label' => 'Preis pro Lead (brutto für den Käufer)',
                'preview' => 'Sie erhalten :net pro Lead (Provision :percent %).',
                'change_hint' => 'Preisänderungen gelten für neue Käufe. Bereits gekaufte Leads behalten ihren Preis.',
                'range' => 'Zulässig sind :min bis :max.',
                'out_of_range' => 'Der Preis muss zwischen :min und :max liegen.',
                'saved' => 'Preis gespeichert.',
                'submit' => 'Preis speichern',
            ],

            'balance' => [
                'available' => 'Auszahlungsguthaben',
                'available_hint' => 'Abgerechnete Einnahmen, die du dir auszahlen lassen kannst.',
                'pending' => 'In Reservierung',
                'pending_badge' => 'ausstehend',
                'pending_hint' => 'Dein Anteil an Leads, die noch nicht abgerechnet sind. Kein Guthaben: Wird ein Kauf aufgelöst, entfällt der Betrag.',
                'earnings' => 'Einnahmen der letzten :days Tage',
                'earnings_hint' => 'Summe der gutgeschriebenen Einnahmen, abzüglich zurückgebuchter Leads.',
            ],

            'history' => [
                'heading' => 'Transaktionsverlauf',
                'empty' => 'Noch keine Buchungen.',
                'date' => 'Datum',
                'type' => 'Art',
                'description' => 'Vorgang',
                'amount' => 'Betrag',
                'balance_after' => 'Saldo danach',
                'show_more' => 'Weitere anzeigen',
            ],

            'payout' => [
                'heading' => 'Auszahlung anfordern',
                'description' => 'Ab :minimum kannst du dir dein Guthaben auszahlen lassen. Der Betrag wird sofort abgebucht und in den nächsten Werktagen überwiesen.',
                'iban_label' => 'IBAN',
                'iban_missing' => 'Keine hinterlegt',
                'iban_cta' => 'IBAN in den Workspace-Einstellungen hinterlegen',
                'amount_label' => 'Betrag',
                'amount_hint' => 'Verfügbar sind :available, mindestens :minimum.',
                'below_minimum' => 'Eine Auszahlung ist erst ab :minimum möglich. Verfügbar sind zurzeit :available.',
                'confirm' => 'Auszahlung jetzt anfordern? Der Betrag wird sofort von deinem Guthaben abgebucht.',
                'submit' => 'Auszahlung anfordern',
                'invalid_amount' => 'Bitte einen gültigen Betrag eintragen.',
                'requested' => 'Auszahlung über :amount angefordert.',
                'history_heading' => 'Bisherige Auszahlungen',
                'history_empty' => 'Noch keine Auszahlung angefordert.',
                'requested_at' => 'Angefordert',
                'amount' => 'Betrag',
                'status' => 'Status',
                'note' => 'Anmerkung',
            ],

        ],

        'errors' => [
            'not_updatable' => 'Eine Wallet-Buchung kann nach dem Anlegen nicht mehr geändert werden.',
            'not_deletable' => 'Eine Wallet-Buchung kann nicht gelöscht werden.',
            'insufficient_reserve' => 'Ihr verfügbares Guthaben reicht nicht aus. Fehlbetrag: :missing.',
            'insufficient_balance' => 'Das Guthaben reicht für diese Buchung nicht: vorhanden sind :balance, benötigt werden :required.',
            'release_exceeds_reserved' => 'Es sind nur :reserved reserviert, aufgelöst werden sollen :requested.',
            'purchase_blocked' => 'Dein Konto ist gesperrt, bis der offene Betrag ausgeglichen ist.',
        ],

        'descriptions' => [
            'topup' => 'Aufladung über Bestellung :order',
            'reserve' => 'Reservierung für Lead #:lead',
            'release' => 'Reservierung für Lead #:lead aufgelöst',
            'release_for_capture' => 'Reservierung für Lead #:lead zur Abbuchung aufgelöst',
            'capture' => 'Abbuchung für Lead #:lead',
            'earning' => 'Einnahme aus Lead #:lead',
            'commission' => 'Provision aus Lead #:lead',
            'refund' => 'Erstattung für Lead #:lead',
            'earning_reversal' => 'Rückbuchung der Einnahme aus Lead #:lead',
            'commission_reversal' => 'Rückbuchung der Provision aus Lead #:lead',
            'surcharge' => 'Aufschlag Pay as you go aus Lead #:lead',
            'surcharge_reversal' => 'Rückbuchung des Aufschlags aus Lead #:lead',
            'chargeback' => 'Rückbuchung der Aufladung aus Bestellung :order',
            'payout' => 'Auszahlung #:payout',
            'payout_rejected' => 'Rückbuchung der abgelehnten Auszahlung #:payout',
            'settlement' => 'Einzug des offenen Betrags (Abrechnung #:settlement)',
            'settlement_return' => 'Rückgabe der Zahlung zu Abrechnung #:settlement',
            'fee' => [
                'settlement_failed' => 'Mahngebühr nach fehlgeschlagenem Einzug',
                'sepa_return' => 'Rücklastschriftgebühr',
                'chargeback' => 'Gebühr für angefochtene Kartenzahlung',
                'no_payment_method' => 'Gebühr: kein Zahlungsmittel hinterlegt',
                'payment_method_revoked' => 'Gebühr: Zahlungsmittel widerrufen',
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | Auszahlung an den Verkaeufer (LP-WALLET-010)
        |----------------------------------------------------------------------
        */

        'payout' => [

            'status' => [
                'requested' => 'Angefordert',
                'paid' => 'Ausgezahlt',
                'rejected' => 'Abgelehnt',
            ],

            'profile' => [
                'heading' => 'Bankverbindung',
                'description' => 'Auf dieses Konto zahlen wir deine Einnahmen aus verkauften Leads aus.',
                'iban' => 'IBAN',
                'iban_helper' => 'Noch keine IBAN hinterlegt. Ohne sie können wir nicht auszahlen.',
                'iban_stored' => 'Hinterlegt: •••• :last4. Zum Ändern neue IBAN eintragen, sonst leer lassen.',
                'invalid_iban' => 'Das ist keine gültige IBAN.',
            ],

            'errors' => [
                'missing_iban' => 'Für eine Auszahlung brauchen wir deine IBAN. Trage sie in deinen Profildaten ein.',
                'below_minimum' => 'Eine Auszahlung ist erst ab :minimum möglich; angefordert wurden :amount.',
                'exceeds_balance' => 'So viel steht nicht zur Verfügung: auszahlbar sind :available, angefordert wurden :amount.',
            ],

            'mail' => [

                'amount_label' => 'Betrag',
                'iban_label' => 'IBAN',
                'seller_label' => 'Verkäufer',
                'reference_label' => 'Vorgang',
                'note_label' => 'Begründung',

                'requested' => [
                    'subject' => 'Auszahlung angefordert: :amount von :seller',
                    'label' => 'Auszahlung',
                    'heading' => 'Neue Auszahlungsanforderung',
                    'intro' => ':seller hat eine Auszahlung über :amount angefordert. Der Betrag ist bereits vom Guthaben abgebucht.',
                    'hint' => 'Überweise den Betrag und markiere die Anforderung anschließend im Admin-Bereich als ausgezahlt. Bei einer Ablehnung wird das Guthaben automatisch zurückgebucht.',
                ],

                'processed' => [

                    'label' => 'Auszahlung',

                    'paid' => [
                        'subject' => 'Deine Auszahlung über :amount ist unterwegs',
                        'heading' => 'Auszahlung überwiesen',
                        'intro' => 'Wir haben deine Auszahlung über :amount überwiesen.',
                        'hint' => 'Je nach Bank dauert die Gutschrift ein bis zwei Werktage.',
                    ],

                    'rejected' => [
                        'subject' => 'Deine Auszahlung über :amount wurde abgelehnt',
                        'heading' => 'Auszahlung abgelehnt',
                        'intro' => 'Deine Auszahlung über :amount konnten wir nicht ausführen. Der Betrag steht wieder in deinem Guthaben.',
                        'hint' => 'Du kannst die Auszahlung jederzeit erneut anfordern, sobald die Ursache behoben ist.',
                    ],

                ],

            ],

        ],

        'opening_balance' => [
            'description' => 'Eröffnungssaldo aus dem bisherigen Guthaben (:credits Credits).',
        ],

        'settlement' => [

            'mail' => [
                'subject' => 'Leadkauf nicht abgerechnet: Lead #:lead',
                'label' => 'Leadabrechnung',
                'heading' => 'Ein entschiedener Lead konnte nicht abgerechnet werden',
                'intro' => 'Die Erreichbarkeit von Lead #:lead steht auf ":status", die Geldseite des Kaufs ist aber nach mehreren Versuchen offen geblieben.',
                'reason' => 'Fehler',
                'outro' => 'Bitte den Kaufbeleg prüfen: Eine Reservierung, die niemand auflöst, blockiert das Guthaben des Käufers.',
            ],

        ],

        'verify' => [

            'mail' => [
                'subject' => 'Wallet-Prüfung: :count Abweichung(en) gegenüber dem Ledger',
                'label' => 'Wallet-Prüfung',
                'heading' => 'Salden weichen vom Ledger ab',
                'intro' => 'Die tägliche Konsistenzprüfung hat Wallets gefunden, deren fortgeschriebener Saldo nicht der Summe seiner Buchungen entspricht.',
                'checked' => ':count Wallet(s) geprüft.',
                'wallet' => 'Wallet #:id',
                'balance' => 'Saldo',
                'reserved' => 'Reserviert',
                'expected' => 'Soll',
                'actual' => 'Ist',
                'findings' => 'Verletzte Postpaid-Regeln',
                'repaired' => 'Der Stand wurde in diesem Lauf auf das Ledger zurückgesetzt.',
                'reservation_totals' => 'Die reservierten Beträge der Käufer-Wallets passen nicht zu den offenen Leadkäufen: Soll :expected, Ist :actual.',
                'outro' => 'Maßgeblich ist immer das Ledger. Bitte die Ursache klären, bevor der Stand korrigiert wird.',
            ],

        ],

    ],

];
