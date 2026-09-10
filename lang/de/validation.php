<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Sprachzeilen der Validierung
    |--------------------------------------------------------------------------
    |
    | Geduzt, wie im uebrigen Dashboard. Der Platzhalter :attribute traegt den
    | Feldnamen; die deutschen Feldnamen stehen unten unter "attributes".
    |
    */

    'accepted' => ':attribute muss akzeptiert werden.',
    'accepted_if' => ':attribute muss akzeptiert werden, wenn :other den Wert :value hat.',
    'active_url' => ':attribute ist keine gültige Internetadresse.',
    'after' => ':attribute muss ein Datum nach :date sein.',
    'after_or_equal' => ':attribute muss ein Datum nach oder gleich :date sein.',
    'alpha' => ':attribute darf nur Buchstaben enthalten.',
    'alpha_dash' => ':attribute darf nur Buchstaben, Zahlen, Binde- und Unterstriche enthalten.',
    'alpha_num' => ':attribute darf nur Buchstaben und Zahlen enthalten.',
    'array' => ':attribute muss eine Liste sein.',
    'ascii' => ':attribute darf nur einbyteige Zeichen und Symbole enthalten.',
    'before' => ':attribute muss ein Datum vor :date sein.',
    'before_or_equal' => ':attribute muss ein Datum vor oder gleich :date sein.',
    'between' => [
        'array' => ':attribute muss zwischen :min und :max Einträge haben.',
        'file' => ':attribute muss zwischen :min und :max Kilobyte groß sein.',
        'numeric' => ':attribute muss zwischen :min und :max liegen.',
        'string' => ':attribute muss zwischen :min und :max Zeichen lang sein.',
    ],
    'boolean' => ':attribute muss "ja" oder "nein" sein.',
    'can' => ':attribute enthält einen unzulässigen Wert.',
    'confirmed' => 'Die Bestätigung zu :attribute stimmt nicht überein.',
    'contains' => 'In :attribute fehlt ein erforderlicher Wert.',
    'current_password' => 'Das Passwort ist falsch.',
    'date' => ':attribute ist kein gültiges Datum.',
    'date_equals' => ':attribute muss ein Datum gleich :date sein.',
    'date_format' => ':attribute entspricht nicht dem Format :format.',
    'decimal' => ':attribute muss :decimal Nachkommastellen haben.',
    'declined' => ':attribute muss abgelehnt werden.',
    'declined_if' => ':attribute muss abgelehnt werden, wenn :other den Wert :value hat.',
    'different' => ':attribute und :other müssen sich unterscheiden.',
    'digits' => ':attribute muss :digits Stellen haben.',
    'digits_between' => ':attribute muss zwischen :min und :max Stellen haben.',
    'dimensions' => ':attribute hat unzulässige Bildabmessungen.',
    'distinct' => ':attribute enthält einen doppelten Wert.',
    'doesnt_end_with' => ':attribute darf nicht mit einem der folgenden Werte enden: :values.',
    'doesnt_start_with' => ':attribute darf nicht mit einem der folgenden Werte beginnen: :values.',
    'email' => ':attribute muss eine gültige E-Mail-Adresse sein.',
    'ends_with' => ':attribute muss mit einem der folgenden Werte enden: :values.',
    'enum' => 'Der gewählte Wert für :attribute ist ungültig.',
    'exists' => 'Der gewählte Wert für :attribute ist ungültig.',
    'extensions' => ':attribute muss eine der folgenden Dateiendungen haben: :values.',
    'file' => ':attribute muss eine Datei sein.',
    'filled' => ':attribute muss ausgefüllt sein.',
    'gt' => [
        'array' => ':attribute muss mehr als :value Einträge haben.',
        'file' => ':attribute muss größer als :value Kilobyte sein.',
        'numeric' => ':attribute muss größer als :value sein.',
        'string' => ':attribute muss länger als :value Zeichen sein.',
    ],
    'gte' => [
        'array' => ':attribute muss mindestens :value Einträge haben.',
        'file' => ':attribute muss mindestens :value Kilobyte groß sein.',
        'numeric' => ':attribute muss mindestens :value sein.',
        'string' => ':attribute muss mindestens :value Zeichen lang sein.',
    ],
    'hex_color' => ':attribute muss eine gültige Hex-Farbe sein.',
    'image' => ':attribute muss ein Bild sein.',
    'in' => 'Der gewählte Wert für :attribute ist ungültig.',
    'in_array' => ':attribute kommt in :other nicht vor.',
    'integer' => ':attribute muss eine ganze Zahl sein.',
    'ip' => ':attribute muss eine gültige IP-Adresse sein.',
    'ipv4' => ':attribute muss eine gültige IPv4-Adresse sein.',
    'ipv6' => ':attribute muss eine gültige IPv6-Adresse sein.',
    'json' => ':attribute muss gültiges JSON sein.',
    'list' => ':attribute muss eine Liste sein.',
    'lowercase' => ':attribute darf nur Kleinbuchstaben enthalten.',
    'lt' => [
        'array' => ':attribute muss weniger als :value Einträge haben.',
        'file' => ':attribute muss kleiner als :value Kilobyte sein.',
        'numeric' => ':attribute muss kleiner als :value sein.',
        'string' => ':attribute muss kürzer als :value Zeichen sein.',
    ],
    'lte' => [
        'array' => ':attribute darf höchstens :value Einträge haben.',
        'file' => ':attribute darf höchstens :value Kilobyte groß sein.',
        'numeric' => ':attribute darf höchstens :value sein.',
        'string' => ':attribute darf höchstens :value Zeichen lang sein.',
    ],
    'mac_address' => ':attribute muss eine gültige MAC-Adresse sein.',
    'max' => [
        'array' => ':attribute darf höchstens :max Einträge haben.',
        'file' => ':attribute darf höchstens :max Kilobyte groß sein.',
        'numeric' => ':attribute darf höchstens :max sein.',
        'string' => ':attribute darf höchstens :max Zeichen lang sein.',
    ],
    'max_digits' => ':attribute darf höchstens :max Stellen haben.',
    'mimes' => ':attribute muss eine Datei vom Typ :values sein.',
    'mimetypes' => ':attribute muss eine Datei vom Typ :values sein.',
    'min' => [
        'array' => ':attribute muss mindestens :min Einträge haben.',
        'file' => ':attribute muss mindestens :min Kilobyte groß sein.',
        'numeric' => ':attribute muss mindestens :min sein.',
        'string' => ':attribute muss mindestens :min Zeichen lang sein.',
    ],
    'min_digits' => ':attribute muss mindestens :min Stellen haben.',
    'missing' => ':attribute darf nicht vorhanden sein.',
    'missing_if' => ':attribute darf nicht vorhanden sein, wenn :other den Wert :value hat.',
    'missing_unless' => ':attribute darf nur vorhanden sein, wenn :other den Wert :value hat.',
    'missing_with' => ':attribute darf nicht vorhanden sein, wenn :values vorhanden ist.',
    'missing_with_all' => ':attribute darf nicht vorhanden sein, wenn :values vorhanden sind.',
    'multiple_of' => ':attribute muss ein Vielfaches von :value sein.',
    'not_in' => 'Der gewählte Wert für :attribute ist ungültig.',
    'not_regex' => ':attribute hat ein ungültiges Format.',
    'numeric' => ':attribute muss eine Zahl sein.',
    'password' => [
        'letters' => ':attribute muss mindestens einen Buchstaben enthalten.',
        'mixed' => ':attribute muss mindestens einen Groß- und einen Kleinbuchstaben enthalten.',
        'numbers' => ':attribute muss mindestens eine Zahl enthalten.',
        'symbols' => ':attribute muss mindestens ein Sonderzeichen enthalten.',
        'uncompromised' => ':attribute ist in einem Datenleck aufgetaucht. Bitte wähle ein anderes.',
    ],
    'phone' => ':attribute muss eine gültige Rufnummer sein.',
    'present' => ':attribute muss vorhanden sein.',
    'present_if' => ':attribute muss vorhanden sein, wenn :other den Wert :value hat.',
    'present_unless' => ':attribute muss vorhanden sein, außer :other hat den Wert :value.',
    'present_with' => ':attribute muss vorhanden sein, wenn :values vorhanden ist.',
    'present_with_all' => ':attribute muss vorhanden sein, wenn :values vorhanden sind.',
    'prohibited' => ':attribute ist nicht zulässig.',
    'prohibited_if' => ':attribute ist nicht zulässig, wenn :other den Wert :value hat.',
    'prohibited_if_accepted' => ':attribute ist nicht zulässig, wenn :other akzeptiert wurde.',
    'prohibited_if_declined' => ':attribute ist nicht zulässig, wenn :other abgelehnt wurde.',
    'prohibited_unless' => ':attribute ist nur zulässig, wenn :other einen der Werte :values hat.',
    'prohibits' => ':attribute schließt :other aus.',
    'recaptcha' => 'Die Captcha-Prüfung ist fehlgeschlagen. Bitte versuche es erneut.',
    'regex' => ':attribute hat ein ungültiges Format.',
    'required' => ':attribute ist ein Pflichtfeld.',
    'required_array_keys' => ':attribute muss Einträge für :values enthalten.',
    'required_if' => ':attribute ist ein Pflichtfeld, wenn :other den Wert :value hat.',
    'required_if_accepted' => ':attribute ist ein Pflichtfeld, wenn :other akzeptiert wurde.',
    'required_if_declined' => ':attribute ist ein Pflichtfeld, wenn :other abgelehnt wurde.',
    'required_unless' => ':attribute ist ein Pflichtfeld, außer :other hat einen der Werte :values.',
    'required_with' => ':attribute ist ein Pflichtfeld, wenn :values vorhanden ist.',
    'required_with_all' => ':attribute ist ein Pflichtfeld, wenn :values vorhanden sind.',
    'required_without' => ':attribute ist ein Pflichtfeld, wenn :values nicht vorhanden ist.',
    'required_without_all' => ':attribute ist ein Pflichtfeld, wenn keiner der Werte :values vorhanden ist.',
    'same' => ':attribute und :other müssen übereinstimmen.',
    'size' => [
        'array' => ':attribute muss genau :size Einträge haben.',
        'file' => ':attribute muss genau :size Kilobyte groß sein.',
        'numeric' => ':attribute muss genau :size sein.',
        'string' => ':attribute muss genau :size Zeichen lang sein.',
    ],
    'starts_with' => ':attribute muss mit einem der folgenden Werte beginnen: :values.',
    'string' => ':attribute muss Text sein.',
    'timezone' => ':attribute muss eine gültige Zeitzone sein.',
    'unique' => ':attribute ist bereits vergeben.',
    'uploaded' => ':attribute konnte nicht hochgeladen werden.',
    'uppercase' => ':attribute darf nur Großbuchstaben enthalten.',
    'url' => ':attribute muss eine gültige Internetadresse sein.',
    'ulid' => ':attribute muss eine gültige ULID sein.',
    'uuid' => ':attribute muss eine gültige UUID sein.',

    /*
    |--------------------------------------------------------------------------
    | Eigene Meldungen je Feld
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'eigene Meldung',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Feldnamen
    |--------------------------------------------------------------------------
    |
    | Ohne diese Liste stuende in jeder Meldung der technische Feldname. Hier
    | stehen nur die Felder, die im Dashboard tatsaechlich vorkommen.
    |
    */

    'attributes' => [
        'address' => 'Adresse',
        'address_line_1' => 'Adresszeile 1',
        'address_line_2' => 'Adresszeile 2',
        'amount' => 'Betrag',
        'city' => 'Ort',
        'code' => 'Code',
        'country' => 'Land',
        'country_code' => 'Land',
        'created_at' => 'Erstellt am',
        'current_password' => 'Aktuelles Passwort',
        'date' => 'Datum',
        'description' => 'Beschreibung',
        'discount_code' => 'Rabattcode',
        'email' => 'E-Mail-Adresse',
        'emails' => 'E-Mail-Adressen',
        'first_name' => 'Vorname',
        'id' => 'ID',
        'label' => 'Beschriftung',
        'last_name' => 'Nachname',
        'name' => 'Name',
        'notification_emails' => 'E-Mail-Adressen',
        'password' => 'Passwort',
        'password_confirmation' => 'Passwortbestätigung',
        'phone' => 'Telefonnummer',
        'phone_number' => 'Telefonnummer',
        'price' => 'Preis',
        'quantity' => 'Menge',
        'reason' => 'Grund',
        'role' => 'Rolle',
        'slug' => 'Kurzname',
        'state' => 'Bundesland',
        'tax_number' => 'Steuernummer',
        'title' => 'Titel',
        'updated_at' => 'Geändert am',
        'url' => 'Internetadresse',
        'zip' => 'Postleitzahl',
    ],

];
