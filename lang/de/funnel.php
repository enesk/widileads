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

];
