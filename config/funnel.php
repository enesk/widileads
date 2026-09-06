<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Funnel Builder
    |--------------------------------------------------------------------------
    |
    | Zentrale Konfiguration der Funnel-Builder-Plattform. Alle Schwellwerte
    | des Funnel-/Lead-Geschaefts stehen hier und ausschliesslich hier. Kein
    | Service, Job, Command oder Livewire-Component darf einen dieser Werte
    | hartkodieren -- immer ueber config('funnel.*') lesen.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Feature-Flags (FB-001)
    |--------------------------------------------------------------------------
    |
    | Mitgelieferte SaaSykit-Module, die der Funnel Builder nicht benoetigt.
    | Die Module bleiben im Code erhalten und werden ausschliesslich ueber
    | diese Flags deaktiviert (Default: aus). Ist ein Flag aus, werden weder
    | die oeffentlichen Routen noch die Navigationseintraege registriert.
    |
    */

    'features' => [

        // Oeffentlicher Blog (/blog) inkl. Admin-Resources fuer Posts & Kategorien.
        'blog' => env('FUNNEL_FEATURE_BLOG_ENABLED', false),

        // Oeffentliche Roadmap (/roadmap) inkl. Voting und Admin-Resource.
        'roadmap' => env('FUNNEL_FEATURE_ROADMAP_ENABLED', false),

        // Announcement-Banner im Frontend/Dashboard inkl. Admin-Resource.
        'announcements' => env('FUNNEL_FEATURE_ANNOUNCEMENTS_ENABLED', false),

        // Referral-Programm (Empfehlungs-Codes, Rewards) inkl. Admin- & Dashboard-Resources.
        'referral' => env('FUNNEL_FEATURE_REFERRAL_ENABLED', false),

    ],

    /*
    |--------------------------------------------------------------------------
    | Destruktive Artisan-Befehle (FB-003)
    |--------------------------------------------------------------------------
    |
    | Der DestructiveCommandGuard blockt migrate:fresh, migrate:refresh,
    | migrate:reset und db:wipe in jeder Umgebung. Einzige Ausnahme:
    | APP_ENV=testing UND FUNNEL_ALLOW_DESTRUCTIVE=1 -- damit die Testsuite
    | ihre Datenbank weiterhin selbst aufbauen kann.
    |
    */

    // Hauptschalter des DestructiveCommandGuard. Nur zusammen mit APP_ENV=testing
    // wirksam, in allen anderen Umgebungen wird der Wert ignoriert.
    'allow_destructive_commands' => env('FUNNEL_ALLOW_DESTRUCTIVE', false),

    /*
    |--------------------------------------------------------------------------
    | Audit-Log (FB-005)
    |--------------------------------------------------------------------------
    |
    | Sicherheitsrelevante Vorgaenge werden ueber App\Services\AuditLogger in
    | der Tabelle audit_logs protokolliert. Eintraege sind unveraenderlich.
    |
    */

    'audit' => [

        // Salt fuer den SHA-256-Hash der IP-Adresse. Die Roh-IP wird niemals
        // gespeichert oder geloggt, nur ihr gesalzener Hash. Ohne eigenen Wert
        // dient APP_KEY als Salt; ein Wechsel des Salts macht alte Hashes
        // unvergleichbar (gewollt, z. B. nach einem Leak).
        'ip_salt' => env('FUNNEL_AUDIT_IP_SALT', ''),

        // Payload-Schluessel, deren Werte vor dem Speichern durch einen
        // Platzhalter ersetzt werden. Schuetzt davor, dass Passwoerter, Tokens
        // oder Roh-IPs versehentlich im Audit-Log landen.
        'redacted_payload_keys' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env(
                'FUNNEL_AUDIT_REDACTED_PAYLOAD_KEYS',
                'password,password_confirmation,current_password,token,api_token,access_token,plain_text_token,secret,authorization,ip,ip_address,client_ip,remote_addr,x_forwarded_for',
            )),
        ))),

    ],

    /*
    |--------------------------------------------------------------------------
    | API (FB-006)
    |--------------------------------------------------------------------------
    */

    'api' => [

        // Gueltigkeitsdauer eines Tenant-API-Tokens in Tagen. 0 bedeutet: kein
        // Ablauf, das Token gilt bis es widerrufen wird.
        'token_expiration_days' => (int) env('FUNNEL_API_TOKEN_EXPIRATION_DAYS', 0),

        // Maximale Anzahl gleichzeitig gueltiger API-Tokens je Tenant.
        'max_tokens_per_tenant' => (int) env('FUNNEL_API_MAX_TOKENS_PER_TENANT', 10),

        // Oeffentliche API-Dokumentation unter /docs/api (FB-030a). Ist der
        // Schalter aus, wird weder die Seite noch die Auslieferung der
        // Spezifikation als Route registriert.
        'docs_enabled' => (bool) env('FUNNEL_API_DOCS_ENABLED', true),

        // Der Betrachter, der docs/openapi.yaml im Browser darstellt. Bewusst
        // ueber ein CDN und nicht als Abhaengigkeit: die Spezifikation ist das
        // Erzeugnis, der Betrachter nur ihre Anzeige.
        'docs_viewer_url' => (string) env(
            'FUNNEL_API_DOCS_VIEWER_URL',
            'https://cdn.jsdelivr.net/npm/@scalar/api-reference@1/dist/browser/standalone.min.js',
        ),

    ],

    /*
    |--------------------------------------------------------------------------
    | Leads
    |--------------------------------------------------------------------------
    */

    'lead' => [

        // Standard-Verkaufspreis eines Leads in EUR, falls weder Funnel noch
        // Kaeufer-Vertrag einen abweichenden Preis definieren.
        'default_price' => (float) env('FUNNEL_LEAD_DEFAULT_PRICE', 15.00),

        // Minuten, die ein Lead fuer einen Kaeufer exklusiv reserviert bleibt,
        // bevor die Reservierung verfaellt und der Lead wieder freigegeben wird.
        'reservation_ttl' => (int) env('FUNNEL_LEAD_RESERVATION_TTL', 10),

        // Tage, die ein Lead samt personenbezogener Daten aufbewahrt wird,
        // bevor er anonymisiert/geloescht wird (DSGVO-Aufbewahrungsfrist).
        'retention_days' => (int) env('FUNNEL_LEAD_RETENTION_DAYS', 730),

        // Tage ohne Fortschritt, nach denen ein Lead als "abgestanden" gilt
        // und nicht mehr zum vollen Preis verkauft wird.
        'stale_after_days' => (int) env('FUNNEL_LEAD_STALE_AFTER_DAYS', 3),

        // Uhrzeit (HH:MM), zu der der taegliche Aufbewahrungslauf startet
        // (app:apply-lead-retention, FB-037). Bewusst nachts: der Lauf
        // schreibt Zustandswechsel und anonymisiert.
        'retention_run_at' => (string) env('FUNNEL_LEAD_RETENTION_RUN_AT', '03:15'),

        // Mindestlaenge der Pflichtbegruendung, wenn ein Operator-Admin den
        // Zustand eines Leads von Hand setzt (FB-036). Zu kurze Begruendungen
        // sind wertlos, sobald jemand den Vorgang spaeter nachvollziehen will.
        'manual_state_min_justification_length' => (int) env('FUNNEL_LEAD_MANUAL_STATE_MIN_JUSTIFICATION', 10),

        // Anzahl Leads, die der Aufbewahrungslauf je Durchgang aus der
        // Datenbank holt. Begrenzt den Speicherbedarf, nicht die Gesamtmenge:
        // der Lauf arbeitet so viele Durchgaenge, wie noetig sind.
        'retention_chunk_size' => (int) env('FUNNEL_LEAD_RETENTION_CHUNK_SIZE', 500),

    ],

    /*
    |--------------------------------------------------------------------------
    | Oeffentliche Funnel-Endpunkte
    |--------------------------------------------------------------------------
    */

    'public' => [

        // Maximale Anzahl oeffentlicher Funnel-Submits pro IP und Stunde
        // (Rate-Limiting gegen Bots und Massen-Einreichungen).
        'rate_limit_per_hour' => (int) env('FUNNEL_PUBLIC_RATE_LIMIT_PER_HOUR', 20),

        // Tage, innerhalb derer eine Anfrage mit derselben E-Mail-Adresse oder
        // Telefonnummer im selben Funnel als moegliche Dublette gilt. Der Lead
        // entsteht trotzdem und traegt nur einen Verweis -- ueber Dublette oder
        // ernst gemeinte Neuanfrage entscheidet der Pruefjob (FB-033).
        'duplicate_window_days' => (int) env('FUNNEL_PUBLIC_DUPLICATE_WINDOW_DAYS', 30),

        // Mindestdauer in Sekunden zwischen Funnel-Start und Absenden. Wird
        // schneller abgeschickt, gilt die Einreichung als Bot (Zeit-Honeypot).
        'min_seconds_before_submit' => (int) env('FUNNEL_PUBLIC_MIN_SECONDS_BEFORE_SUBMIT', 30),

        // Minuten ohne Aktivitaet, nach denen eine angefangene Strecke als
        // abgebrochen gilt. Der Teilfortschritt bleibt erhalten -- kehrt der
        // Endkunde zurueck, laeuft dieselbe Sitzung weiter.
        'abandon_after_minutes' => (int) env('FUNNEL_PUBLIC_ABANDON_AFTER_MINUTES', 30),

        // Maximale Laenge gespeicherter Herkunftsangaben (UTM-Parameter,
        // Referrer, Embed-Origin). Laengere Werte werden gekuerzt -- sie kommen
        // von aussen und sollen weder die Spalte sprengen noch als Ablage
        // fuer fremde Inhalte taugen.
        'origin_max_length' => (int) env('FUNNEL_PUBLIC_ORIGIN_MAX_LENGTH', 255),

        // Maximale Laenge des gespeicherten User-Agent. Fuer die Auswertung
        // (Geraetetyp, Browser) reicht der Anfang; der Rest ist Ballast.
        'user_agent_max_length' => (int) env('FUNNEL_PUBLIC_USER_AGENT_MAX_LENGTH', 255),

    ],

    /*
    |--------------------------------------------------------------------------
    | Telefonische Kontaktaufnahme durch den Kaeufer
    |--------------------------------------------------------------------------
    */

    'call' => [

        // Ab dieser Gespraechsdauer in Sekunden gilt ein Anruf als angenommen
        // (kuerzere Verbindungen zaehlen als nicht erreicht).
        'answered_after_seconds' => (int) env('FUNNEL_CALL_ANSWERED_AFTER_SECONDS', 30),

        // Anzahl erfolgloser Anrufversuche, nach denen der Kaeufer seine
        // Kontaktpflicht erfuellt hat und der Lead nicht reklamiert werden kann.
        'max_failed_attempts' => (int) env('FUNNEL_CALL_MAX_FAILED_ATTEMPTS', 3),

        // Mindestabstand in Stunden zwischen zwei Anrufversuchen, damit
        // Versuche als eigenstaendig gezaehlt werden.
        'min_gap_hours' => (int) env('FUNNEL_CALL_MIN_GAP_HOURS', 2),

        // Mindestanzahl unterschiedlicher Kalendertage, an denen angerufen
        // worden sein muss.
        'min_days' => (int) env('FUNNEL_CALL_MIN_DAYS', 2),

        // Tage ab Lead-Zustellung, innerhalb derer der Kaeufer die
        // Kontaktversuche abgeschlossen haben muss.
        'deadline_days' => (int) env('FUNNEL_CALL_DEADLINE_DAYS', 7),

    ],

    /*
    |--------------------------------------------------------------------------
    | Feldschluessel-Aliase (FB-010)
    |--------------------------------------------------------------------------
    |
    | Der Feldschluessel einer Frage wird beim Speichern normalisiert (Label
    | "E-Mail" ergibt zunaechst "e_mail"). Danach wird er ueber diese Tabelle auf
    | den reservierten Kontakt-Feldschluessel abgebildet, damit ein Funnel den
    | Kontakt auch dann liefert, wenn sein Ersteller eine gebraeuchliche
    | Schreibweise gewaehlt hat.
    |
    | Ohne diese Aufloesung entstuende ein Lead ohne aufloesbare Kontaktdaten:
    | LeadContact (FB-032) sucht nach "email", die Frage hiesse aber "e_mail".
    | Der Fehler faellt erst in Produktion auf, wenn die unbrauchbaren Leads
    | bereits in der Datenbank liegen.
    |
    | Schluessel = normalisierte Schreibweise, Wert = reservierter Feldschluessel
    | aus App\Constants\FunnelFieldKey. Ziele, die dort nicht existieren, werden
    | ignoriert.
    |
    */

    'field_key_aliases' => [

        'e_mail' => 'email',
        'mail' => 'email',
        'email_adresse' => 'email',
        'e_mail_adresse' => 'email',

        'telefonnummer' => 'telefon',
        'tel' => 'telefon',
        'mobil' => 'telefon',
        'handy' => 'telefon',

        'postleitzahl' => 'plz',
        'plz_ort' => 'plz',

        'vor_name' => 'vorname',

        'nach_name' => 'nachname',
        'familienname' => 'nachname',

        'datenschutz' => 'einwilligung',
        'zustimmung' => 'einwilligung',
        'einwilligung_datenschutz' => 'einwilligung',

    ],

    /*
    |--------------------------------------------------------------------------
    | Fragetypen (FB-011)
    |--------------------------------------------------------------------------
    |
    | Grenzen und Muster, mit denen die Handler in app/Funnel/QuestionTypes die
    | Antworten eines Endkunden pruefen und vereinheitlichen.
    |
    */

    'question' => [

        // Region, gegen die Telefonnummern ohne Landesvorwahl gelesen werden.
        // Gespeichert wird immer E.164; "0151 1234567" ergibt "+491511234567".
        'default_phone_region' => env('FUNNEL_QUESTION_DEFAULT_PHONE_REGION', 'DE'),

        // Muster einer gueltigen Postleitzahl. Vorgabe: fuenf Ziffern (DE).
        'postal_code_pattern' => env('FUNNEL_QUESTION_POSTAL_CODE_PATTERN', '/^[0-9]{5}$/'),

        // Maximale Laenge einzeiliger Freitextantworten.
        'text_max_length' => (int) env('FUNNEL_QUESTION_TEXT_MAX_LENGTH', 255),

        // Maximale Laenge mehrzeiliger Freitextantworten.
        'textarea_max_length' => (int) env('FUNNEL_QUESTION_TEXTAREA_MAX_LENGTH', 2000),

    ],

    /*
    |--------------------------------------------------------------------------
    | Auslieferung der Strecke (FB-012)
    |--------------------------------------------------------------------------
    */

    'runtime' => [

        // Zyklenschutz des StepResolver: Ein Weg durch den Funnel darf hoechstens
        // so viele Schritte lang sein wie der Funnel Schritte hat, mal diesem
        // Faktor. Fuehren die Verzweigungsregeln im Kreis, bricht die Auswertung
        // danach ab, statt den Endkunden in einer Schleife haengen zu lassen.
        'max_step_visit_factor' => (int) env('FUNNEL_RUNTIME_MAX_STEP_VISIT_FACTOR', 2),

    ],

    /*
    |--------------------------------------------------------------------------
    | Veroeffentlichung eines Funnels (FB-014)
    |--------------------------------------------------------------------------
    */

    'publish' => [

        // Kontakt-Feldschluessel, von denen mindestens einer im Funnel stehen
        // muss, damit er veroeffentlicht werden darf. Ohne erreichbaren
        // Kontaktweg entstuenden Leads, die kein Kaeufer erreichen kann.
        'required_contact_field_keys' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('FUNNEL_PUBLISH_REQUIRED_CONTACT_FIELD_KEYS', 'email,telefon')),
        ))),

    ],

    /*
    |--------------------------------------------------------------------------
    | Oeffentliche Strecke (FB-020)
    |--------------------------------------------------------------------------
    */

    'runtime_public' => [

        // Zeigt ein archivierter Funnel eine Hinweisseite statt 404? Ein Funnel,
        // der auf fremden Seiten eingebettet war, soll nicht kommentarlos
        // verschwinden. Der Vollausbau der Hinweisseite ist FB-018.
        'show_notice_for_archived' => (bool) env('FUNNEL_RUNTIME_SHOW_NOTICE_FOR_ARCHIVED', true),

    ],

    /*
    |--------------------------------------------------------------------------
    | Vorschau eines Funnels (FB-018)
    |--------------------------------------------------------------------------
    |
    | Der Vorschau-Link ist signiert und befristet. Er zeigt auch Entwuerfe, die
    | noch nie veroeffentlicht wurden -- der Link selbst ist die Zugangskontrolle,
    | deshalb soll er nicht lange gelten.
    |
    */

    'preview' => [

        // Gueltigkeitsdauer eines Vorschau-Links in Minuten.
        'link_ttl_minutes' => (int) env('FUNNEL_PREVIEW_LINK_TTL_MINUTES', 30),

    ],

    /*
    |--------------------------------------------------------------------------
    | Lead-Marktplatz (FB-051)
    |--------------------------------------------------------------------------
    */

    'marketplace' => [

        'profile' => [

            // Hoechstzahl automatischer Kaeufe je Kalendertag, mit der ein neu
            // angelegtes Kaufprofil startet. 0 bedeutet: keine Begrenzung.
            // Ausgewertet wird das Limit erst vom Autokauf in FB-056.
            'default_daily_limit' => (int) env('FUNNEL_MARKETPLACE_DEFAULT_DAILY_LIMIT', 0),

            // Hoechstzahl an Postleitzahl-Praefixen je Profil. Begrenzt die
            // Groesse des JSON-Felds und die Laenge der spaeteren Abfrage.
            'max_postal_prefixes' => (int) env('FUNNEL_MARKETPLACE_MAX_POSTAL_PREFIXES', 50),

            // Hoechstlaenge eines Postleitzahl-Praefixes. Fuenf Stellen sind in
            // Deutschland die vollstaendige Postleitzahl -- laenger waere kein
            // Praefix mehr.
            'postal_prefix_max_length' => (int) env('FUNNEL_MARKETPLACE_POSTAL_PREFIX_MAX_LENGTH', 5),

        ],

    ],

];
