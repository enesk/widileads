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
            'lead_purchased' => 'Lead gekauft',
            'lead_state_forced' => 'Lead-Status zwangsweise gesetzt',
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

];
