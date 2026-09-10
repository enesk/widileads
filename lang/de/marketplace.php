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
    | Guthaben (FB-052)
    |--------------------------------------------------------------------------
    */

    'credit' => [

        // FB-092: Guthaben aufladen.
        'top_up' => [
            'title' => 'Guthaben aufladen',
            'nav_label' => 'Guthaben',
            'balance_label' => 'Dein Guthaben',
            'balance_value' => ':credits Guthaben',
            'unit_price_hint' => 'Ein Guthaben kostet :price €.',
            'description' => 'Mit Guthaben kaufst du Leads im Marktplatz. Wähle ein Paket, die Zahlung läuft über Stripe. Nach erfolgreicher Zahlung wird das Guthaben sofort gutgeschrieben.',
            'package_credits' => ':credits Guthaben',
            'buy' => 'Jetzt aufladen',
            'empty' => 'Zurzeit sind keine Guthabenpakete hinterlegt.',
            'payment_hint' => 'Die Zahlung wird über Stripe abgewickelt. Rechnung und Zahlungsbeleg findest du unter „Bestellungen“.',
        ],

        'type' => [
            'purchase' => 'Kauf',
            'debit' => 'Abbuchung',
            'refund' => 'Gutschrift',
            'adjustment' => 'Korrektur',
        ],

        'resource' => [
            'label' => 'Guthabenbuchung',
            'plural_label' => 'Guthabenkonto',
            'empty_heading' => 'Noch keine Buchungen',
            'empty_description' => 'Buchungen erscheinen hier, sobald ein Käufer Guthaben kauft oder verbraucht.',
            'read_only' => 'Buchungen sind unveränderlich. Eine Fehlbuchung wird durch eine Gegenbuchung korrigiert, nicht durch Überschreiben.',
        ],

        'fields' => [
            'created_at' => 'Zeitpunkt',
            'tenant' => 'Käufer',
            'type' => 'Art',
            'credits' => 'Guthaben',
            'amount_cents' => 'Betrag in Cent',
            'reference' => 'Beleg',
        ],

        'hints' => [
            'credits' => 'Positiv schreibt gut, negativ bucht ab. Null ist nicht zulässig - eine Buchung ohne Wirkung gehört nicht ins Journal.',
            'amount_cents' => 'Nur ausfüllen, wenn hinter der Buchung tatsächlich Geld steht, etwa bei Guthaben auf Rechnung.',
        ],

        'actions' => [
            'adjust' => 'Guthaben buchen',
            'adjust_description' => 'Manuelle Korrektur des Guthabens. Auch der Weg für Guthaben auf Rechnung: Der vereinbarte Betrag wird hier von Hand gebucht.',
            'adjusted' => 'Buchung wurde angelegt.',
        ],

        'errors' => [
            'not_updatable' => 'Eine Guthabenbuchung kann nach dem Anlegen nicht mehr geändert werden.',
            'not_deletable' => 'Eine Guthabenbuchung kann nicht gelöscht werden.',
            'insufficient' => 'Das Guthaben reicht nicht: verfügbar sind :balance, benötigt werden :requested.',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Marktplatz (FB-053)
    |--------------------------------------------------------------------------
    */

    'listing' => [

        'balance_label' => 'Guthaben',
        'balance_value' => ':credits Leads',
        'result_count' => '{0} Kein Lead passt zu deinen Kriterien|{1} 1 Lead passt zu deinen Kriterien|[2,*] :count Leads passen zu deinen Kriterien',
        'empty_hint' => 'Neue Anfragen erscheinen hier automatisch. Erweitere deine Kaufkriterien, um mehr Leads zu sehen.',
        'no_credits' => 'Dein Guthaben ist aufgebraucht. Lade auf, um Leads zu kaufen.',
        'badge_new' => 'Neu',
        'locked_contact' => 'Telefon und E-Mail nach dem Kauf',
        'price' => 'Kostet :credits Lead',
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

        'balance' => 'Guthaben: :credits Leads',
        'confirm' => 'Diesen Lead jetzt kaufen? Es wird ein Guthaben abgebucht und die Kontaktdaten werden freigegeben.',
        'done' => 'Lead gekauft. Die Kontaktdaten sind jetzt sichtbar, die Bestätigung ist unterwegs.',

        'errors' => [
            'already_taken' => 'Dieser Lead ist inzwischen vergeben. Ein anderer Käufer war schneller.',
            'buyer_not_approved' => 'Dieser Workspace ist noch nicht für den Marktplatz freigeschaltet.',
            'already_bought' => 'Diesen Lead hast du bereits gekauft.',
            'own_lead' => 'Dieser Lead stammt aus einem eigenen Fragebogen und kann nicht gekauft werden.',
        ],

        'mail' => [
            'subject' => 'Dein gekaufter Lead',
            'heading' => 'Lead gekauft',
            'intro' => 'Du hast einen Lead aus dem Fragebogen ":funnel" gekauft. Die Kontaktdaten stehen unten.',
            'contact_heading' => 'Kontaktdaten',
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
        'detail' => [
            'subheading' => 'Aus :funnel, gekauft am :date',
            'facts' => 'Zum Kauf',
            'back' => 'Zurück zu meinen Leads',
            'open' => 'Öffnen',
            'no_answers' => 'Zu diesem Lead wurden keine weiteren Angaben übermittelt.',
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
        'description' => 'Was ein Käufer in einem Monat bekommen hat und was daraus geworden ist. Bezahlt wird im Voraus per Guthaben - abgerechnet wird hier nichts, es wird Rechenschaft abgelegt.',
        'buyer' => 'Käufer',
        'month' => 'Monat',
        'no_buyer' => 'Es ist kein Käufer angelegt.',
        'export' => 'Als CSV herunterladen',

        'leads' => 'Leads nach Zustand',
        'money' => 'Guthaben und Umsatz',
        'purchases' => 'Käufe insgesamt',
        'revenue' => 'Umsatz',
        'credits_purchased' => 'Guthaben gekauft',
        'credits_debited' => 'Guthaben verbraucht',
        'credits_refunded' => 'Guthaben zurückgebucht',
        'credits_hint' => 'Verbrauchtes Guthaben und Zahl der Käufe müssen übereinstimmen. Weichen sie ab, stimmt etwas nicht.',

        'invoices' => 'Rechnungen',
        'invoices_hint' => 'Die Rechnungen zu den Guthabenkäufen dieses Monats. Ausgestellt hat sie SaaSykit beim Kauf des Pakets.',
        'no_invoices' => 'In diesem Monat wurde kein Guthaben gekauft.',

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

];
