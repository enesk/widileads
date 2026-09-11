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

    'listing' => [

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
            'newest' => 'Zuletzt gekauft zuerst',
            'oldest' => 'Zuerst gekauft zuerst',
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
            'chargeback' => 'Rückbuchung der Aufladung aus Bestellung :order',
            'payout' => 'Auszahlung #:payout',
            'payout_rejected' => 'Rückbuchung der abgelehnten Auszahlung #:payout',
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
                'repaired' => 'Der Stand wurde in diesem Lauf auf das Ledger zurückgesetzt.',
                'reservation_totals' => 'Die reservierten Beträge der Käufer-Wallets passen nicht zu den offenen Leadkäufen: Soll :expected, Ist :actual.',
                'outro' => 'Maßgeblich ist immer das Ledger. Bitte die Ursache klären, bevor der Stand korrigiert wird.',
            ],

        ],

    ],

];
