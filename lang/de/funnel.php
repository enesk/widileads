<?php

return [

    'destructive' => [
        'description' => 'Dieser Befehl ist im Funnel Builder dauerhaft gesperrt.',
        'blocked' => 'Der Befehl ":command" ist im Funnel Builder gesperrt und wurde abgebrochen. Es wurden keine Daten geloescht.',
        'hint' => 'Datenvernichtende Befehle sind in JEDER Umgebung gesperrt - auch mit --force. Nur die Testsuite darf sie ausfuehren (APP_ENV=testing und FUNNEL_ALLOW_DESTRUCTIVE=1). Nutze stattdessen "php artisan migrate".',
    ],

    'tenant_type' => [
        'label' => 'Workspace-Typ',
        'helper' => 'Betreiber bauen Funnels, Kaeufer kaufen die daraus entstehenden Leads. Der Typ laesst sich nachtraeglich nicht aendern.',
        'operator' => 'Betreiber',
        'buyer' => 'Kaeufer',
        'no_tenant' => 'Fuer diese Seite ist ein aktiver Workspace erforderlich.',
        'forbidden' => 'Diese Seite steht Workspaces vom Typ ":type" nicht zur Verfuegung.',
    ],

    'audit' => [

        'resource' => [
            'label' => 'Audit-Eintrag',
            'plural_label' => 'Audit-Log',
            'empty_heading' => 'Noch keine Audit-Eintraege',
            'empty_description' => 'Sicherheitsrelevante Vorgaenge erscheinen hier, sobald sie stattfinden.',
        ],

        'fields' => [
            'created_at' => 'Zeitpunkt',
            'action' => 'Vorgang',
            'tenant' => 'Mandant',
            'user' => 'Handelnder Benutzer',
            'subject' => 'Betroffener Datensatz',
            'subject_type' => 'Typ',
            'subject_id' => 'Kennung',
            'payload' => 'Details',
            'ip_hash' => 'IP-Hash (SHA-256)',
        ],

        'filters' => [
            'action' => 'Vorgang',
            'tenant' => 'Mandant',
            'from' => 'Von',
            'until' => 'Bis',
        ],

        'hints' => [
            'read_only' => 'Audit-Eintraege sind unveraenderlich und koennen weder angelegt noch bearbeitet oder geloescht werden.',
            'ip_hash' => 'Es wird nie die IP-Adresse gespeichert, sondern nur ihr gesalzener SHA-256-Hash.',
        ],

        'actions' => [
            'user_logged_in' => 'Anmeldung',
            'tenant_switched' => 'Mandant gewechselt',
            'role_assigned' => 'Rolle zugewiesen',
            'role_revoked' => 'Rolle entzogen',
            'api_token_created' => 'API-Token erstellt',
            'api_token_deleted' => 'API-Token geloescht',
            'data_exported' => 'Daten exportiert',
            'data_erased' => 'Daten anonymisiert (Loeschersuchen)',
            'lead_purchased' => 'Lead gekauft',
            'lead_state_forced' => 'Lead-Status zwangsweise gesetzt',
            'buyer_approved' => 'Kaeufer freigeschaltet',
            'buyer_rejected' => 'Kaeufer abgelehnt',
        ],

        'errors' => [
            'not_updatable' => 'Ein Audit-Eintrag kann nach dem Anlegen nicht mehr geaendert werden.',
            'not_deletable' => 'Ein Audit-Eintrag kann nicht geloescht werden.',
        ],

    ],

    'api_token' => [
        'heading' => 'API-Zugaenge',
        'nav_label' => 'API-Zugaenge',
        'description' => 'Tokens gehoeren diesem Workspace und erreichen ausschliesslich dessen Daten.',
        'empty' => 'Noch keine API-Zugaenge angelegt.',
        'name' => 'Bezeichnung',
        'name_placeholder' => 'z. B. Website-Einbettung',
        'name_helper' => 'Wofuer wird das Token verwendet? Zum Beispiel "Website-Einbettung" oder "CRM-Anbindung".',
        'abilities' => 'Berechtigungen',
        'abilities_helper' => 'Nur die hier gewaehlten Berechtigungen sind mit diesem Token moeglich.',
        'last_used_at' => 'Zuletzt verwendet',
        'never_used' => 'Noch nie verwendet',
        'created_at' => 'Erstellt am',
        'create' => 'Token erstellen',
        'created' => 'Token wurde erstellt.',
        'revoke' => 'Widerrufen',
        'revoke_confirm' => 'Das Token wird sofort ungueltig. Anwendungen, die es verwenden, verlieren den Zugriff.',
        'revoked' => 'Token wurde widerrufen.',
        'revoke_failed' => 'Token konnte nicht widerrufen werden.',
        'plain_text_heading' => 'Token nur jetzt sichtbar',
        'plain_text_hint' => 'Kopiere das Token jetzt. Es wird nur als Hash gespeichert und laesst sich spaeter nicht erneut anzeigen.',
        'plain_text_dismiss' => 'Verstanden, ausblenden',
        'limit_reached' => 'Es sind hoechstens :limit gleichzeitig gueltige Tokens je Workspace moeglich. Widerrufe zuerst ein bestehendes Token.',
        'no_tenant_token' => 'Das verwendete Token gehoert zu keinem Workspace.',
        'ability' => [
            'funnels_read' => 'Funnels lesen',
            'funnels_write' => 'Funnels schreiben',
            'leads_read' => 'Leads lesen',
            'webhooks_manage' => 'Webhooks verwalten',
        ],
    ],

    'status' => [
        'draft' => 'Entwurf',
        'published' => 'Veroeffentlicht',
        'archived' => 'Archiviert',
    ],

    'api_docs' => [
        'title' => 'API-Dokumentation',
        'noscript' => 'Fuer die Darstellung der API-Dokumentation wird JavaScript benoetigt.',
        'download' => 'OpenAPI-Spezifikation herunterladen',
        'spec_missing' => 'Die OpenAPI-Spezifikation wurde nicht gefunden.',
    ],

    'condition_operator' => [
        'equals' => 'ist gleich',
        'not_equals' => 'ist ungleich',
        'in' => 'ist eine von',
        'gt' => 'ist groesser als',
        'lt' => 'ist kleiner als',
        'contains' => 'enthaelt',
        'answered' => 'wurde beantwortet',
        'score_gte' => 'Punktzahl mindestens',
    ],

    'lead' => [

        // Zustaende des Lead-Lebenszyklus (App\Constants\LeadState).
        'state' => [
            'neu' => 'Neu',
            'verfuegbar' => 'Verfuegbar',
            'reserviert' => 'Reserviert',
            'verkauft' => 'Verkauft',
            'erreicht' => 'Erreicht',
            'unerreichbar' => 'Unerreichbar',
            'ungueltig' => 'Ungueltig',
            'abgelaufen' => 'Abgelaufen',
        ],

        // Gruende eines Zustandswechsels (App\Constants\LeadTransitionReason).
        'reason' => [
            'screening_passed' => 'Pruefung bestanden',
            'duplicate' => 'Dublette',
            'implausible_contact' => 'Kontaktdaten nicht plausibel',
            'spam' => 'Als Spam erkannt',
            'reserved_by_buyer' => 'Von Kaeufer reserviert',
            'reservation_expired' => 'Reservierung abgelaufen',
            'reservation_released' => 'Reservierung aufgehoben',
            'purchased' => 'Gekauft',
            'call_answered' => 'Anruf angenommen',
            'call_attempts_exhausted' => 'Anrufversuche ausgeschoepft',
            'complaint_approved' => 'Reklamation bestaetigt',
            'complaint_period_elapsed' => 'Reklamationsfrist verstrichen',
            'retention_elapsed' => 'Aufbewahrungsfrist erreicht',
            'manual_override' => 'Manuell gesetzt',
        ],

        'errors' => [
            'illegal_transition' => 'Ein Lead im Zustand ":from" kann nicht nach ":to" wechseln. Moeglich waere: :allowed.',
            'no_transition_allowed' => 'kein Wechsel mehr (Endzustand)',
            'concurrent_transition' => 'Der Lead war beim Wechsel nach ":to" nicht mehr im erwarteten Zustand ":expected", sondern in ":actual". Ein anderer Vorgang war schneller.',
            'log_not_updatable' => 'Ein Eintrag im Zustandsprotokoll kann nach dem Anlegen nicht mehr geaendert werden.',
            'log_not_deletable' => 'Ein Eintrag im Zustandsprotokoll kann nicht geloescht werden.',
            'justification_too_short' => 'Die Begruendung muss mindestens :min Zeichen lang sein.',
            'force_state_forbidden' => 'Nur Betreiber-Administratoren duerfen den Zustand eines Leads von Hand setzen.',
        ],

        // Lead-Uebersicht im Admin-Panel (FB-036).
        'resource' => [
            'label' => 'Lead',
            'plural_label' => 'Leads',
            'empty_heading' => 'Noch keine Leads',
            'empty_description' => 'Leads entstehen aus abgeschlossenen Funnel-Anfragen.',
        ],

        'fields' => [
            'id' => 'Kennung',
            'tenant' => 'Mandant',
            'lead_state' => 'Zustand',
            'settled_price' => 'Festgeschriebener Preis',
            'created_at' => 'Eingegangen am',
            'anonymized_at' => 'Anonymisiert am',
        ],

        // Manuelle Statussetzung (FB-036).
        'force_state' => [
            'action' => 'Status setzen',
            'heading' => 'Zustand von Hand setzen',
            'description' => 'Der Wechsel wird im Zustandsprotokoll und im Audit-Log festgehalten. Angeboten werden nur Zustaende, die vom aktuellen Zustand aus erlaubt sind.',
            'target' => 'Neuer Zustand',
            'justification' => 'Begruendung',
            'justification_helper' => 'Mindestens :min Zeichen. Wer den Vorgang spaeter nachvollzieht, liest genau diesen Text.',
            'submit' => 'Zustand setzen',
            'done' => 'Der Lead steht jetzt auf ":state".',
        ],

        // Aufbewahrungsfrist (FB-037).
        'retention' => [
            'summary' => 'Aufbewahrungslauf (:days Tage): :expired Lead(s) abgelaufen, :anonymized Lead(s) anonymisiert.',
        ],

    ],

    'question_type' => [
        'single_choice' => 'Einfachauswahl',
        'multi_choice' => 'Mehrfachauswahl',
        'text' => 'Text (einzeilig)',
        'textarea' => 'Text (mehrzeilig)',
        'number' => 'Zahl',
        'email' => 'E-Mail-Adresse',
        'phone' => 'Telefonnummer',
        'date' => 'Datum',
        'postal_code' => 'Postleitzahl',
        'image_choice' => 'Bildauswahl',
        'slider' => 'Schieberegler',
        'consent' => 'Einwilligung',
        'info' => 'Hinweistext (ohne Eingabe)',
    ],

    'condition' => [
        'errors' => [
            'cycle_detected' => 'Die Verzweigungsregeln dieses Funnels fuehren im Kreis: Schritt :step wurde nach :max Schritten erneut erreicht.',
        ],
    ],

    'result' => [
        'errors' => [
            'invalid_range' => 'Der Ergebnisbereich ":title" ist ungueltig: Die Untergrenze :min liegt ueber der Obergrenze :max.',
            'overlap' => 'Die Ergebnisbereiche ":first" (:first_range) und ":second" (:second_range) ueberschneiden sich.',
            'gap_single' => 'Fuer die Punktzahl :score gibt es kein Ergebnis.',
            'gap_range' => 'Fuer die Punktzahlen :from bis :to gibt es kein Ergebnis.',
        ],
    ],

    'version' => [
        'errors' => [
            'not_publishable' => 'Dieser Funnel kann nicht veroeffentlicht werden. :reasons',
            'no_steps' => 'Der Funnel hat keinen einzigen Schritt.',
            'no_contact_field' => 'Dem Funnel fehlt ein Kontaktfeld - mindestens eines von: :fields.',
            'not_updatable' => 'Eine veroeffentlichte Funnel-Version kann nicht mehr geaendert werden.',
            'not_deletable' => 'Eine veroeffentlichte Funnel-Version kann nicht geloescht werden.',
        ],
    ],

    // DSGVO-Auskunft und Loeschersuchen (FB-038).
    'gdpr' => [
        'nav_label' => 'Datenschutz-Anfragen',
        'heading' => 'Auskunft und Loeschersuchen',
        'section_heading' => 'Anfragen betroffener Personen',
        'section_body' => 'Hier werden Auskunftsersuchen (Art. 15 DSGVO) und Loeschersuchen (Art. 17 DSGVO) bearbeitet. Gesucht wird ueber die E-Mail-Adresse, die die Person im Funnel angegeben hat.',
        'section_hint' => 'Eine Loeschung entfernt den Personenbezug, nicht den Datensatz: Zustand, festgeschriebener Preis und Zeitstempel bleiben erhalten, damit Abrechnungen der Vergangenheit stimmig bleiben.',
        'email' => 'E-Mail-Adresse',
        'email_helper' => 'Die Adresse, die die Person im Funnel angegeben hat. Gross- und Kleinschreibung spielt keine Rolle.',
        'export' => [
            'action' => 'Auskunft erteilen',
            'heading' => 'Auskunft als JSON herunterladen',
            'description' => 'Ausgegeben wird alles, was zu dieser Adresse gespeichert ist: die Leads mit allen Feldern, ihr Zustandsprotokoll und ihre Antworten. Der Vorgang wird im Audit-Log festgehalten.',
            'submit' => 'Herunterladen',
            'done' => 'Auskunft zu :count Lead(s) erstellt.',
            'empty' => 'Zu dieser Adresse sind keine Daten gespeichert.',
        ],
        'erase' => [
            'action' => 'Daten loeschen',
            'heading' => 'Loeschersuchen ausfuehren',
            'description' => 'Der Personenbezug aller Leads dieser Adresse wird entfernt. Das laesst sich nicht rueckgaengig machen. Der Vorgang wird im Audit-Log festgehalten.',
            'submit' => 'Personenbezug entfernen',
            'done' => 'Personenbezug von :count Lead(s) entfernt.',
            'empty' => 'Zu dieser Adresse sind keine Daten gespeichert.',
        ],
    ],

    'runtime' => [
        'progress' => 'Fortschritt',
        'next' => 'Weiter',
        'submit' => 'Anfrage absenden',
        'continue' => 'Weiter zu den Kontaktdaten',
        'thanks_title' => 'Vielen Dank!',
        'thanks_body' => 'Deine Anfrage ist eingegangen. Ein passender Anbieter meldet sich bei dir.',
        'archived_title' => 'Diese Anfrage ist nicht mehr verfuegbar',
        'archived_body' => 'Der Fragebogen wurde archiviert und kann nicht mehr ausgefuellt werden.',

        'errors' => [
            'phone_not_dialable' => 'Diese Telefonnummer konnten wir nicht lesen. Bitte gib sie mit Vorwahl an.',
        ],
    ],

];
