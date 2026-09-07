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
            'pending' => 'In Pruefung',
            'active' => 'Freigeschaltet',
            'rejected' => 'Abgelehnt',
        ],

        'not_approved' => 'Dieser Workspace ist noch nicht fuer den Marktplatz freigeschaltet.',

        'form' => [
            'heading' => 'Als Kaeufer registrieren',
            'description' => 'Nach dem Absenden pruefen wir die Angaben und schalten den Zugang zum Lead-Marktplatz frei.',
            'company_name' => 'Firma',
            'contact_name' => 'Ansprechpartner',
            'contact_email' => 'E-Mail des Ansprechpartners',
            'contact_phone' => 'Telefon (optional)',
            'broker_register_number' => 'Vermittlerregister-Nr. (optional)',
            'broker_register_number_helper' => 'Registrierungsnummer nach Paragraf 34d GewO, falls vorhanden. Kaeufer ohne Vermittlerzulassung lassen das Feld leer.',
            'vat_id' => 'Umsatzsteuer-Identifikationsnummer',
            'av_accepted' => 'Ich bestaetige den Auftragsverarbeitungsvertrag.',
            'av_accepted_helper' => 'Ohne diese Bestaetigung duerfen wir keine personenbezogenen Lead-Daten an dich uebermitteln.',
            'submit' => 'Registrierung absenden',
            'already_registered' => 'Fuer dieses Konto liegt bereits eine Registrierung vor.',
            'received_heading' => 'Registrierung eingegangen',
            'received_text' => 'Wir haben die Registrierung fuer ":company" erhalten.',
            'received_hint' => 'Sobald der Zugang freigeschaltet ist, erreichst du den Lead-Marktplatz. Bis dahin sind keine Leads sichtbar.',
        ],

        'validation' => [
            'required' => 'Bitte fuelle das Feld ":attribute" aus.',
            'email' => 'Bitte gib unter ":attribute" eine gueltige E-Mail-Adresse an.',
            'max' => 'Das Feld ":attribute" ist zu lang.',
            'vat_id' => 'Die Umsatzsteuer-Identifikationsnummer beginnt mit dem Laendercode, z. B. DE123456789.',
            'av_accepted' => 'Ohne die Bestaetigung des Auftragsverarbeitungsvertrags koennen wir den Zugang nicht anlegen.',
        ],

        'resource' => [
            'label' => 'Kaeufer-Registrierung',
            'plural_label' => 'Kaeufer-Registrierungen',
            'empty_heading' => 'Noch keine Registrierungen',
            'empty_description' => 'Registrierungen erscheinen hier, sobald sich ein Kaeufer angemeldet hat.',
            'read_only' => 'Die Angaben stammen vom Kaeufer und werden nicht bearbeitet. Moeglich sind Freischalten und Ablehnen.',
        ],

        'fields' => [
            'company_name' => 'Firma',
            'status' => 'Status',
            'contact_name' => 'Ansprechpartner',
            'contact_email' => 'E-Mail',
            'contact_phone' => 'Telefon',
            'vat_id' => 'USt-IdNr.',
            'broker_register_number' => 'Vermittlerregister-Nr.',
            'av_accepted_at' => 'AV-Vertrag bestaetigt am',
            'tenant' => 'Workspace',
            'created_at' => 'Eingegangen am',
            'reviewed_by' => 'Entschieden von',
            'reviewed_at' => 'Entschieden am',
            'rejection_reason' => 'Begruendung der Ablehnung',
        ],

        'hints' => [
            'broker_register_number' => 'Optional: nicht jeder Kaeufer ist Versicherungsvermittler. Im Zweifel im Einzelfall pruefen.',
            'rejection_reason' => 'Mindestens 10 Zeichen. Die Begruendung wird dem Kaeufer angezeigt.',
        ],

        'actions' => [
            'approve' => 'Freischalten',
            'approve_confirm' => 'Der Kaeufer erhaelt damit Zugang zum Lead-Marktplatz.',
            'approved' => 'Kaeufer wurde freigeschaltet.',
            'reject' => 'Ablehnen',
            'rejected' => 'Kaeufer wurde abgelehnt.',
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
        'description' => 'Lege fest, welche Leads du sehen willst. Ein leeres Feld schraenkt nicht ein - ohne Angabe siehst du alles.',
        'saved' => 'Kaufkriterien wurden gespeichert.',
        'submit' => 'Speichern',

        'funnels' => 'Fragebogen',
        'funnels_helper' => 'Ohne Auswahl siehst du Leads aus allen Fragebogen.',
        'no_funnels' => 'Zurzeit ist kein Fragebogen veroeffentlicht.',

        'postal_prefixes' => 'Regionen',
        'postal_prefixes_helper' => 'Anfaenge von Postleitzahlen, mit Komma getrennt. "76" deckt alles von 76001 bis 76999 ab.',

        'answer_filters' => 'Antwortfilter',
        'answer_filters_helper' => 'Je Zeile ein Feldschluessel und die Antworten, die du akzeptierst. Bei einer Mehrfachauswahl genuegt eine Uebereinstimmung.',
        'field_key_placeholder' => 'z. B. tierart',
        'values_placeholder' => 'z. B. hund, katze',
        'add_filter' => 'Filter hinzufuegen',
        'remove_filter' => 'Entfernen',
        'no_answer_filters' => 'Noch kein Antwortfilter - du siehst Leads mit beliebigen Antworten.',

        'min_score' => 'Mindestpunktzahl',
        'min_score_helper' => 'Leer: keine Untergrenze. Leads ohne Punktzahl fallen raus, sobald hier ein Wert steht.',

        'daily_limit' => 'Tageslimit',
        'daily_limit_helper' => 'Hoechstzahl automatischer Kaeufe je Tag. Leer oder 0: keine Begrenzung.',

        'auto_buy' => 'Passende Leads automatisch kaufen',
        'auto_buy_helper' => 'Ist der Autokauf aus, siehst du passende Leads im Marktplatz und entscheidest selbst.',

        'notify_email' => 'Benachrichtigung an',
        'notify_email_helper' => 'Ohne Angabe verwenden wir die Adresse aus deiner Registrierung.',

        'validation' => [
            'too_many_prefixes' => 'Es sind hoechstens :max Regionen moeglich.',
            'prefix_format' => '":prefix" ist keine Region: erlaubt sind Ziffern, hoechstens :max Stellen.',
            'notify_email' => 'Bitte gib eine gueltige E-Mail-Adresse an.',
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
            'empty_description' => 'Buchungen erscheinen hier, sobald ein Kaeufer Guthaben kauft oder verbraucht.',
            'read_only' => 'Buchungen sind unveraenderlich. Eine Fehlbuchung wird durch eine Gegenbuchung korrigiert, nicht durch Ueberschreiben.',
        ],

        'fields' => [
            'created_at' => 'Zeitpunkt',
            'tenant' => 'Kaeufer',
            'type' => 'Art',
            'credits' => 'Guthaben',
            'amount_cents' => 'Betrag in Cent',
            'reference' => 'Beleg',
        ],

        'hints' => [
            'credits' => 'Positiv schreibt gut, negativ bucht ab. Null ist nicht zulaessig - eine Buchung ohne Wirkung gehoert nicht ins Journal.',
            'amount_cents' => 'Nur ausfuellen, wenn hinter der Buchung tatsaechlich Geld steht, etwa bei Guthaben auf Rechnung.',
        ],

        'actions' => [
            'adjust' => 'Guthaben buchen',
            'adjust_description' => 'Manuelle Korrektur des Guthabens. Auch der Weg fuer Guthaben auf Rechnung: Der vereinbarte Betrag wird hier von Hand gebucht.',
            'adjusted' => 'Buchung wurde angelegt.',
        ],

        'errors' => [
            'not_updatable' => 'Eine Guthabenbuchung kann nach dem Anlegen nicht mehr geaendert werden.',
            'not_deletable' => 'Eine Guthabenbuchung kann nicht geloescht werden.',
            'insufficient' => 'Das Guthaben reicht nicht: verfuegbar sind :balance, benoetigt werden :requested.',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Marktplatz (FB-053)
    |--------------------------------------------------------------------------
    */

    'listing' => [

        'heading' => 'Marktplatz',
        'nav_label' => 'Marktplatz',
        'description' => 'Verfuegbare Leads, die deinen Kaufkriterien entsprechen. Kontaktdaten werden erst nach dem Kauf sichtbar.',
        'no_profile' => 'Du hast noch keine Kaufkriterien hinterlegt - dir werden deshalb alle verfuegbaren Leads angezeigt.',
        'empty' => 'Zurzeit passt kein verfuegbarer Lead zu deinen Kaufkriterien.',

        'sort' => 'Sortierung',
        'sort_newest' => 'Neueste zuerst',
        'sort_score' => 'Hoechste Punktzahl zuerst',
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
        'done' => 'Lead gekauft. Die Kontaktdaten sind jetzt sichtbar, die Bestaetigung ist unterwegs.',

        'errors' => [
            'already_taken' => 'Dieser Lead ist inzwischen vergeben. Ein anderer Kaeufer war schneller.',
            'buyer_not_approved' => 'Dieser Workspace ist noch nicht fuer den Marktplatz freigeschaltet.',
            'own_lead' => 'Dieser Lead stammt aus einem eigenen Fragebogen und kann nicht gekauft werden.',
        ],

        'mail' => [
            'subject' => 'Dein gekaufter Lead',
            'heading' => 'Lead gekauft',
            'intro' => 'Du hast einen Lead aus dem Fragebogen ":funnel" gekauft. Die Kontaktdaten stehen unten.',
            'contact_heading' => 'Kontaktdaten',
            'outro' => 'Melde dich zeitnah -- die Abschlussquote faellt mit jedem Tag, der vergeht.',
        ],

    ],

];
