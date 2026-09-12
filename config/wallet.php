<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Geldbasiertes Wallet mit Ledger (LP-WALLET)
    |--------------------------------------------------------------------------
    |
    | Saemtliche Betraege, Schwellwerte und Prozentsaetze des Wallets. Wallet-
    | Service, PurchaseService, Auszahlung, Portal und Admin lesen ausschliess-
    | lich aus config('wallet.*') -- kein Betrag und kein Prozentsatz dieser
    | Liste darf irgendwo im Code hartkodiert stehen.
    |
    | Alle Geldwerte stehen in Cent (int), damit im Ledger nie ein Float
    | gerechnet wird.
    |
    */

    // Waehrung aller Wallets und Ledger-Buchungen. Das Wallet ist bewusst
    // einwaehrungsfaehig: gemischte Waehrungen in einem Saldo waeren nicht
    // aufsummierbar.
    'currency' => env('WALLET_CURRENCY', 'EUR'),

    // Provision der Plattform je verkauftem Lead, in Prozent des Kaufpreises.
    // Der Rest ist die Einnahme des Verkaeufers.
    'commission_percent' => (int) env('WALLET_COMMISSION_PERCENT', 20),

    // Vorgabepreis (Cent), zu dem ein neu angelegter Verkaeufer-Mandant seine
    // Leads anbietet, solange er keinen eigenen Preis gesetzt hat. Entspricht
    // config('funnel.lead.default_price') von 15,00 EUR. Gesetzt wird der Wert
    // beim Anlegen des Mandanten (App\Observers\TenantObserver); der
    // Spaltendefault in der Migration ist nur der Rueckfall der Datenbank.
    'default_lead_price_cents' => (int) env('WALLET_DEFAULT_LEAD_PRICE_CENTS', 1500),

    // Grenzen (Cent) fuer den vom Verkaeufer selbst gesetzten Leadpreis. Jede
    // Eingabe im Verkaeufer-Portal wird gegen diese Spanne geprueft, damit
    // weder ein Preis 0 noch ein Fantasiepreis ins Wallet gelangt.
    'lead_price_min_cents' => (int) env('WALLET_LEAD_PRICE_MIN_CENTS', 100),
    'lead_price_max_cents' => (int) env('WALLET_LEAD_PRICE_MAX_CENTS', 50000),

    // Mindestbetrag (Cent) einer Aufladung durch den Kaeufer.
    'topup_min_cents' => (int) env('WALLET_TOPUP_MIN_CENTS', 5000),

    // Hoechstbetrag (Cent) einer einzelnen Aufladung. Die Obergrenze ist keine
    // Schikane, sondern schuetzt vor dem verrutschten Komma: eine Aufladung
    // ueber 500.000 EUR ist mit hoher Wahrscheinlichkeit ein Tippfehler und
    // waere ueber den Zahlungsanbieter nur muehsam zurueckzuholen.
    'topup_max_cents' => (int) env('WALLET_TOPUP_MAX_CENTS', 500000),

    // Vorschlaege (Cent) auf der Aufladeseite. Der Kaeufer kann jeden Betrag
    // ab topup_min_cents frei eingeben; diese Werte sind nur die Schaltflaechen
    // fuer den Regelfall.
    'topup_presets_cents' => [5000, 10000, 25000, 50000],

    // Umsatzsteuersatz (Prozent), der auf der Aufladeseite als enthaltener
    // Anteil ausgewiesen wird. Die Angabe ist eine Pflichtangabe des Checkouts;
    // welcher Satz gilt, haengt am Betreiber. 0 laesst die Zeile weg -- so
    // bleibt die Seite fuer Kleinunternehmer richtig, statt einen Satz zu
    // behaupten, der nicht abgerechnet wird.
    'topup_vat_percent' => (int) env('WALLET_TOPUP_VAT_PERCENT', 19),

    // Slug des Einmalkauf-Produkts, ueber das der vorhandene SaaSykit-Checkout
    // eine Aufladung abrechnet (LP-WALLET-009). Das Produkt kostet genau einen
    // Euro; die Menge im Warenkorb ist der Aufladebetrag in Euro. So braucht
    // der freie Betrag kein zweites Zahlungsverfahren neben dem vorhandenen.
    // Gutgeschrieben wird nicht die Menge, sondern der tatsaechlich bezahlte
    // Betrag der Bestellung -- Rabatte bleiben damit automatisch richtig.
    'topup_product_slug' => env('WALLET_TOPUP_PRODUCT_SLUG', 'guthaben-aufladung'),

    // Zahlungsanbieter, an den eine Aufladung unmittelbar weitergeleitet wird
    // (Slug wie in der Tabelle payment_providers: stripe, lemon-squeezy, ...).
    //
    // Ohne diese Angabe muesste der Kaeufer zwischen Aufladeseite und Zahlung
    // noch einen Anbieter auswaehlen -- ein Klick, der ihn nichts entscheiden
    // laesst, was er entscheiden will. Der genannte Anbieter muss aktiv sein
    // und seine Zahlung als Weiterleitung anbieten (isRedirectProvider).
    // Trifft eines davon nicht zu, faellt die Aufladung auf die
    // Checkout-Seite zurueck, statt in einer Fehlermeldung zu enden: Ein
    // Overlay-Anbieter wie Paddle braucht zwingend eine Seite, auf der sein
    // Skript laeuft.
    'topup_payment_provider' => env('WALLET_TOPUP_PAYMENT_PROVIDER', 'stripe'),

    // Mindestbetrag (Cent), ab dem ein Verkaeufer eine Auszahlung anfordern
    // kann.
    'payout_min_cents' => (int) env('WALLET_PAYOUT_MIN_CENTS', 5000),

    // Gegenwert (Cent) eines Credits aus dem alten Guthabensystem. Grundlage
    // der Datenmigration bestehender Guthaben ins Wallet; entspricht dem
    // bisherigen Verkaufspreis eines Credits
    // (config('funnel.marketplace.credits.unit_price') = 5,00 EUR).
    'legacy_credit_value_cents' => (int) env('WALLET_LEGACY_CREDIT_VALUE_CENTS', 500),

    // Kennung des Plattform-Wallets. Das Wallet der Plattform gehoert keinem
    // Mandanten, sondern wird ueber diese feste Kennung gefunden -- dort
    // landen Provisionen und von dort gehen Auszahlungen und Erstattungen aus.
    'platform_wallet_owner' => env('WALLET_PLATFORM_OWNER', 'platform'),

    /*
    |--------------------------------------------------------------------------
    | Pay as you go (Postpaid, LP-POSTPAID)
    |--------------------------------------------------------------------------
    |
    | Kaeufer mit freigeschaltetem Postpaid kaufen gegen einen Kreditrahmen und
    | zahlen im Nachhinein per Lastschrift oder Karte. Saemtliche Betraege,
    | Prozentsaetze und Zeitpunkte dieses Verfahrens stehen hier -- Antrag,
    | Aufschlag, Einzug, Mahnung und Rueckstufung lesen ausschliesslich
    | config('wallet.postpaid.*').
    |
    */
    'postpaid' => [

        // Hauptschalter des Rollouts. Steht er auf false, ist jeder
        // Postpaid-Pfad abgeschaltet: kein Antrag im Portal, kein Aufschlag am
        // Marktplatz, kein Einzug durch den Scheduler. Bereits freigeschaltete
        // Kaeufer werden dadurch NICHT zurueckgestuft und behalten ihre
        // offenen Betraege -- der Schalter steuert nur, ob das Verfahren
        // laeuft, nicht wer es darf.
        'enabled' => (bool) env('WALLET_POSTPAID_ENABLED', false),

        // Aufschlag (Prozent des Leadpreises), den ein Postpaid-Kaeufer je Kauf
        // zusaetzlich zahlt. Er deckt Zahlungsausfall und Zahlungsgebuehren des
        // nachgelagerten Einzugs. Bewusst ein Float: 7,5 % laesst sich nicht als
        // ganze Prozent ausdruecken. Der Aufschlag wird beim Kauf als Snapshot
        // am Kaufbeleg festgehalten, damit eine spaetere Aenderung des Satzes
        // laufende Kaeufe nicht rueckwirkend verteuert.
        'surcharge_percent' => (float) env('WALLET_POSTPAID_SURCHARGE_PERCENT', 7.5),

        // Kreditrahmen (Cent), den ein frisch freigeschalteter Kaeufer erhaelt.
        // Der Rahmen steht am Mandanten und kann vom Admin je Kaeufer
        // abweichend gesetzt werden; dieser Wert ist nur die Vorgabe.
        'default_credit_limit_cents' => (int) env('WALLET_POSTPAID_CREDIT_LIMIT_CENTS', 30000),

        // Offener Betrag (Cent), ab dem sofort eingezogen wird, ohne den
        // woechentlichen Termin abzuwarten. Schuetzt die Plattform davor, dass
        // ein Kaeufer binnen weniger Tage seinen ganzen Rahmen ausschoepft und
        // der Ausfall erst am Wochentermin auffaellt.
        'settlement_threshold_cents' => (int) env('WALLET_POSTPAID_SETTLEMENT_THRESHOLD_CENTS', 15000),

        // Wochentag und Uhrzeit des regulaeren Einzugs (Zeitzone der
        // Anwendung). Der Wochentag ist als englischer Kleinbuchstabenname
        // angegeben, wie ihn der Laravel-Scheduler in ->weeklyOn() erwartet.
        'settlement_weekday' => env('WALLET_POSTPAID_SETTLEMENT_WEEKDAY', 'monday'),
        'settlement_time' => env('WALLET_POSTPAID_SETTLEMENT_TIME', '06:00'),

        // Vorlauffrist (Werktage) der SEPA-Vorabankuendigung
        // (LP-POSTPAID-014). Bei der SEPA-Basislastschrift muss der
        // Zahlungsempfaenger Betrag und Belastungsdatum vorher ankuendigen;
        // ohne diese Ankuendigung darf der Kaeufer die Abbuchung als
        // unberechtigt zurueckgeben -- und genau diese Ruecklastschrift loest
        // bei uns Gebuehr, Sperre und Rueckstufung aus. Der Fehler auf unserer
        // Seite wuerde also den Kaeufer Geld kosten.
        //
        // Gezaehlt werden Werktage, nicht Kalendertage: Eine Ankuendigung am
        // Freitag mit Belastung am Samstag gibt dem Kaeufer keinen Tag Zeit,
        // sein Konto zu decken. Stripe verlangt bei SEPA regelmaessig
        // mindestens einen Werktag Vorlauf, deshalb die Vorgabe 1. Der Wert
        // gilt nur fuer Lastschriften -- bei Karte entfaellt die Ankuendigung
        // (App\Constants\PaymentMethodType::requiresPrenotification()).
        'prenotification_days' => (int) env('WALLET_POSTPAID_PRENOTIFICATION_DAYS', 1),

        // Wartezeit (Tage) bis zum zweiten Einzugsversuch nach einer
        // fehlgeschlagenen Abbuchung. Ein sofortiger Wiederholungsversuch
        // scheitert bei mangelnder Deckung nur ein zweites Mal und kostet
        // erneut Ruecklastschriftgebuehr.
        'retry_after_days' => (int) env('WALLET_POSTPAID_RETRY_AFTER_DAYS', 3),

        // Mahngebuehr (Cent), die bei ausbleibender Zahlung erhoben wird.
        'dunning_fee_cents' => (int) env('WALLET_POSTPAID_DUNNING_FEE_CENTS', 1000),

        // Ruecklastschriftgebuehr (Cent), die der Kaeufer traegt, wenn seine
        // Bank die Abbuchung zurueckgibt.
        'return_fee_cents' => (int) env('WALLET_POSTPAID_RETURN_FEE_CENTS', 1500),

        // SEPA-Lastschriftmandat (LP-POSTPAID-005). Vor der Bestaetigung eines
        // Lastschriftmandats muss der Kaeufer lesen koennen, wer da abbucht --
        // das ist keine Hoeflichkeit, sondern Inhalt des Mandats.
        //
        // `creditor_name` ist der Glaeubiger, wie er auf dem Kontoauszug des
        // Kaeufers erscheint. `creditor_id` ist die Glaeubiger-Identifikations-
        // nummer. Bleibt sie leer, wird im Mandatstext die von Stripe genutzte
        // Kennung genannt: Stripe tritt als Zahlungsdienstleister mit eigener
        // Glaeubiger-ID auf, eine eigene Nummer von der Bundesbank ist dafuer
        // nicht erforderlich. Beantragt der Betreiber spaeter eine eigene,
        // traegt er sie hier ein, ohne dass Code sich aendert.
        'creditor_name' => env('WALLET_POSTPAID_CREDITOR_NAME', 'Enes Kul – Webentwicklung & IT-Dienstleistungen'),
        'creditor_id' => env('WALLET_POSTPAID_CREDITOR_ID', ''),
        'creditor_id_fallback' => 'DE98ZZZ09999999999',

        // Eigenes Webhook-Secret des Postpaid-Endpunkts. Postpaid haengt an
        // einem zweiten Stripe-Webhook (Zahlungsmittel, Mandate, Einzuege) und
        // damit an einem eigenen Secret. Bleibt es leer, gilt das Secret des
        // vorhandenen SaaSykit-Endpunkts -- so laeuft der Fall, dass beide
        // Ereignismengen auf einem Endpunkt eingerichtet sind.
        'webhook_signing_secret' => env('WALLET_POSTPAID_WEBHOOK_SIGNING_SECRET'),

        // Eignung: Mindestzahl abgerechneter (captured) Leadkaeufe, Mindestalter
        // des Kontos in Tagen und Zeitraum in Tagen, in dem die Zahlungshistorie
        // ohne Stoerung sein muss. Wer diese Huerden nicht nimmt, kann Postpaid
        // nicht beantragen.
        'min_captured_purchases' => (int) env('WALLET_POSTPAID_MIN_CAPTURED_PURCHASES', 5),
        'min_account_age_days' => (int) env('WALLET_POSTPAID_MIN_ACCOUNT_AGE_DAYS', 30),
        'clean_history_days' => (int) env('WALLET_POSTPAID_CLEAN_HISTORY_DAYS', 180),

        // Serie der Belegnummern fuer Postpaid-Abrechnungen
        // (LP-POSTPAID-015). Die vollstaendige Nummer ist Serie, Jahr des
        // Einzugs und der aufgefuellte Schluessel des Settlements, also etwa
        // ABR-2026-00042. Eigene Serie und nicht die der Rechnungen aus
        // config('invoices.serial_number.series'): Beide Nummernkreise laufen
        // unabhaengig voneinander, eine gemeinsame Serie mit zwei Zaehlern
        // gaebe doppelte Nummern.
        'settlement_invoice_series' => env('WALLET_POSTPAID_INVOICE_SERIES', 'ABR'),

        // Sperrfrist (Tage) nach einer Ablehnung, bevor derselbe Kaeufer
        // erneut beantragen darf (LP-POSTPAID-006). Ohne sie koennte ein
        // abgelehnter Kaeufer denselben Antrag am naechsten Tag wieder
        // stellen -- die Ablehnungsmail nennt diese Frist ausdruecklich, sie
        // darf also nicht nur ein Satz sein.
        'reapply_after_days' => (int) env('WALLET_POSTPAID_REAPPLY_AFTER_DAYS', 90),

    ],

];
