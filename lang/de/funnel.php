<?php

return [

    'destructive' => [
        'description' => 'Dieser Befehl ist im Funnel Builder dauerhaft gesperrt.',
        'blocked' => 'Der Befehl ":command" ist im Funnel Builder gesperrt und wurde abgebrochen. Es wurden keine Daten gelöscht.',
        'hint' => 'Datenvernichtende Befehle sind in JEDER Umgebung gesperrt - auch mit --force. Nur die Testsuite darf sie ausführen (APP_ENV=testing und FUNNEL_ALLOW_DESTRUCTIVE=1). Nutze stattdessen "php artisan migrate".',
    ],

    'tenant_type' => [
        'label' => 'Workspace-Typ',
        'helper' => 'Betreiber bauen Funnels, Käufer kaufen die daraus entstehenden Leads. Der Typ lässt sich nachträglich nicht ändern.',
        'operator' => 'Betreiber',
        'buyer' => 'Käufer',
        'no_tenant' => 'Für diese Seite ist ein aktiver Workspace erforderlich.',
        'forbidden' => 'Diese Seite steht Workspaces vom Typ ":type" nicht zur Verfügung.',
    ],

    'audit' => [

        'resource' => [
            'label' => 'Audit-Eintrag',
            'plural_label' => 'Audit-Log',
            'empty_heading' => 'Noch keine Audit-Einträge',
            'empty_description' => 'Sicherheitsrelevante Vorgänge erscheinen hier, sobald sie stattfinden.',
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
            'read_only' => 'Audit-Einträge sind unveränderlich und können weder angelegt noch bearbeitet oder gelöscht werden.',
            'ip_hash' => 'Es wird nie die IP-Adresse gespeichert, sondern nur ihr gesalzener SHA-256-Hash.',
        ],

        'actions' => [
            'user_logged_in' => 'Anmeldung',
            'tenant_switched' => 'Mandant gewechselt',
            'role_assigned' => 'Rolle zugewiesen',
            'role_revoked' => 'Rolle entzogen',
            'api_token_created' => 'API-Token erstellt',
            'api_token_deleted' => 'API-Token gelöscht',
            'data_exported' => 'Daten exportiert',
            'data_erased' => 'Daten anonymisiert (Löschersuchen)',
            'lead_purchased' => 'Lead gekauft',
            'lead_state_forced' => 'Lead-Status zwangsweise gesetzt',
            'embed_origin_rejected' => 'Einbettung abgelehnt (fremde Herkunft)',
            'buyer_approved' => 'Käufer freigeschaltet',
            'buyer_rejected' => 'Käufer abgelehnt',
            'caller_id_requested' => 'Rufnummer-Bestätigung angefordert',
            'caller_id_verified' => 'Rufnummer bestätigt',
            'postpaid_approved' => 'Pay as you go freigegeben',
            'postpaid_rejected' => 'Pay as you go abgelehnt',
            'postpaid_credit_limit_changed' => 'Kreditrahmen geändert',
            'postpaid_downgraded' => 'Pay as you go beendet',
        ],

        'errors' => [
            'not_updatable' => 'Ein Audit-Eintrag kann nach dem Anlegen nicht mehr geändert werden.',
            'not_deletable' => 'Ein Audit-Eintrag kann nicht gelöscht werden.',
        ],

    ],

    'api_token' => [
        'heading' => 'API-Zugänge',
        'nav_label' => 'API-Zugänge',
        'description' => 'Tokens gehören diesem Workspace und erreichen ausschließlich dessen Daten.',
        'empty' => 'Noch keine API-Zugänge angelegt.',
        'name' => 'Bezeichnung',
        'name_placeholder' => 'z. B. Website-Einbettung',
        'name_helper' => 'Wofür wird das Token verwendet? Zum Beispiel "Website-Einbettung" oder "CRM-Anbindung".',
        'abilities' => 'Berechtigungen',
        'abilities_helper' => 'Nur die hier gewählten Berechtigungen sind mit diesem Token möglich.',
        'last_used_at' => 'Zuletzt verwendet',
        'never_used' => 'Noch nie verwendet',
        'created_at' => 'Erstellt am',
        'create' => 'Token erstellen',
        'created' => 'Token wurde erstellt.',
        'revoke' => 'Widerrufen',
        'revoke_confirm' => 'Das Token wird sofort ungültig. Anwendungen, die es verwenden, verlieren den Zugriff.',
        'revoked' => 'Token wurde widerrufen.',
        'revoke_failed' => 'Token konnte nicht widerrufen werden.',
        'plain_text_heading' => 'Token nur jetzt sichtbar',
        'plain_text_hint' => 'Kopiere das Token jetzt. Es wird nur als Hash gespeichert und lässt sich später nicht erneut anzeigen.',
        'plain_text_dismiss' => 'Verstanden, ausblenden',
        'limit_reached' => 'Es sind höchstens :limit gleichzeitig gültige Tokens je Workspace möglich. Widerrufe zuerst ein bestehendes Token.',
        'no_tenant_token' => 'Das verwendete Token gehört zu keinem Workspace.',
        'ability' => [
            'funnels_read' => 'Funnels lesen',
            'funnels_write' => 'Funnels schreiben',
            'leads_read' => 'Leads lesen',
            'webhooks_manage' => 'Webhooks verwalten',
        ],
    ],

    'status' => [
        'draft' => 'Entwurf',
        'published' => 'Veröffentlicht',
        'archived' => 'Archiviert',
    ],

    'api_docs' => [
        'management' => 'Management-API',
        'public' => 'Öffentliche Runtime-API',
        'title' => 'API-Dokumentation',
        'noscript' => 'Für die Darstellung der API-Dokumentation wird JavaScript benötigt.',
        'download' => 'OpenAPI-Spezifikation herunterladen',
        'spec_missing' => 'Die OpenAPI-Spezifikation wurde nicht gefunden.',
    ],

    'condition_operator' => [
        'equals' => 'ist gleich',
        'not_equals' => 'ist ungleich',
        'in' => 'ist eine von',
        'gt' => 'ist größer als',
        'lt' => 'ist kleiner als',
        'contains' => 'enthält',
        'answered' => 'wurde beantwortet',
        'score_gte' => 'Punktzahl mindestens',
    ],

    'lead' => [

        // Zustaende des Lead-Lebenszyklus (App\Constants\LeadState).
        'state' => [
            'neu' => 'Neu',
            'verfuegbar' => 'Verfügbar',
            'reserviert' => 'Reserviert',
            'verkauft' => 'Verkauft',
            'erreicht' => 'Erreicht',
            'unerreichbar' => 'Unerreichbar',
            'ungueltig' => 'Ungültig',
            'abgelaufen' => 'Abgelaufen',
        ],

        // Gruende eines Zustandswechsels (App\Constants\LeadTransitionReason).
        'reason' => [
            'screening_passed' => 'Prüfung bestanden',
            'duplicate' => 'Dublette',
            'implausible_contact' => 'Kontaktdaten nicht plausibel',
            'spam' => 'Als Spam erkannt',
            'reserved_by_buyer' => 'Von Käufer reserviert',
            'reservation_expired' => 'Reservierung abgelaufen',
            'reservation_released' => 'Reservierung aufgehoben',
            'purchased' => 'Gekauft',
            'call_answered' => 'Anruf angenommen',
            'call_attempts_exhausted' => 'Anrufversuche ausgeschöpft',
            'complaint_approved' => 'Reklamation bestätigt',
            'complaint_period_elapsed' => 'Reklamationsfrist verstrichen',
            'retention_elapsed' => 'Aufbewahrungsfrist erreicht',
            'manual_override' => 'Manuell gesetzt',
        ],

        'errors' => [
            'illegal_transition' => 'Ein Lead im Zustand ":from" kann nicht nach ":to" wechseln. Möglich wäre: :allowed.',
            'no_transition_allowed' => 'kein Wechsel mehr (Endzustand)',
            'concurrent_transition' => 'Der Lead war beim Wechsel nach ":to" nicht mehr im erwarteten Zustand ":expected", sondern in ":actual". Ein anderer Vorgang war schneller.',
            'log_not_updatable' => 'Ein Eintrag im Zustandsprotokoll kann nach dem Anlegen nicht mehr geändert werden.',
            'log_not_deletable' => 'Ein Eintrag im Zustandsprotokoll kann nicht gelöscht werden.',
            'justification_too_short' => 'Die Begründung muss mindestens :min Zeichen lang sein.',
            'force_state_forbidden' => 'Nur Betreiber-Administratoren dürfen den Zustand eines Leads von Hand setzen.',
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
            'description' => 'Der Wechsel wird im Zustandsprotokoll und im Audit-Log festgehalten. Angeboten werden nur Zustände, die vom aktuellen Zustand aus erlaubt sind.',
            'target' => 'Neuer Zustand',
            'justification' => 'Begründung',
            'justification_helper' => 'Mindestens :min Zeichen. Wer den Vorgang später nachvollzieht, liest genau diesen Text.',
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
            'cycle_detected' => 'Die Verzweigungsregeln dieses Funnels führen im Kreis: Schritt :step wurde nach :max Schritten erneut erreicht.',
        ],
    ],

    'result' => [
        'errors' => [
            'invalid_range' => 'Der Ergebnisbereich ":title" ist ungültig: Die Untergrenze :min liegt über der Obergrenze :max.',
            'overlap' => 'Die Ergebnisbereiche ":first" (:first_range) und ":second" (:second_range) überschneiden sich.',
            'gap_single' => 'Für die Punktzahl :score gibt es kein Ergebnis.',
            'gap_range' => 'Für die Punktzahlen :from bis :to gibt es kein Ergebnis.',
        ],
    ],

    'version' => [
        'errors' => [
            'not_publishable' => 'Dieser Funnel kann nicht veröffentlicht werden. :reasons',
            'no_steps' => 'Der Funnel hat keinen einzigen Schritt.',
            'no_contact_field' => 'Dem Funnel fehlt ein Kontaktfeld - mindestens eines von: :fields.',
            'not_updatable' => 'Eine veröffentlichte Funnel-Version kann nicht mehr geändert werden.',
            'not_deletable' => 'Eine veröffentlichte Funnel-Version kann nicht gelöscht werden.',
        ],
    ],

    // DSGVO-Auskunft und Loeschersuchen (FB-038).
    'gdpr' => [
        'nav_label' => 'Datenschutz-Anfragen',
        'heading' => 'Auskunft und Löschersuchen',
        'section_heading' => 'Anfragen betroffener Personen',
        'section_body' => 'Hier werden Auskunftsersuchen (Art. 15 DSGVO) und Löschersuchen (Art. 17 DSGVO) bearbeitet. Gesucht wird über die E-Mail-Adresse, die die Person im Funnel angegeben hat.',
        'section_hint' => 'Eine Löschung entfernt den Personenbezug, nicht den Datensatz: Zustand, festgeschriebener Preis und Zeitstempel bleiben erhalten, damit Abrechnungen der Vergangenheit stimmig bleiben.',
        'email' => 'E-Mail-Adresse',
        'email_helper' => 'Die Adresse, die die Person im Funnel angegeben hat. Groß- und Kleinschreibung spielt keine Rolle.',
        'export' => [
            'action' => 'Auskunft erteilen',
            'heading' => 'Auskunft als JSON herunterladen',
            'description' => 'Ausgegeben wird alles, was zu dieser Adresse gespeichert ist: die Leads mit allen Feldern, ihr Zustandsprotokoll und ihre Antworten. Der Vorgang wird im Audit-Log festgehalten.',
            'submit' => 'Herunterladen',
            'done' => 'Auskunft zu :count Lead(s) erstellt.',
            'empty' => 'Zu dieser Adresse sind keine Daten gespeichert.',
        ],
        'erase' => [
            'action' => 'Daten löschen',
            'heading' => 'Löschersuchen ausführen',
            'description' => 'Der Personenbezug aller Leads dieser Adresse wird entfernt. Das lässt sich nicht rückgängig machen. Der Vorgang wird im Audit-Log festgehalten.',
            'submit' => 'Personenbezug entfernen',
            'done' => 'Personenbezug von :count Lead(s) entfernt.',
            'empty' => 'Zu dieser Adresse sind keine Daten gespeichert.',
        ],
    ],

];
