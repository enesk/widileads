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

];
