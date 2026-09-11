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

];
