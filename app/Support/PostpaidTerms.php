<?php

declare(strict_types=1);

namespace App\Support;

use App\Constants\PaymentMode;
use App\Models\Wallet;
use Illuminate\Support\Carbon;

/**
 * Die Konditionen von Pay as you go in der Sprache des Kaeufers
 * (LP-POSTPAID-010).
 *
 * Vier Stellen im Portal nennen dieselben Zahlen: der Antrag, die
 * Guthabenanzeige, der Marktplatz und das Sperrband. Ohne diese Klasse stuende
 * der Einzugstermin an vier Stellen im Markup -- und eine Aenderung an
 * config('wallet.postpaid.*') waere an dreien vergessen.
 *
 * Gerechnet wird hier nichts. Die Klasse liest die Konfiguration und formatiert
 * sie; Kreditrahmen, offener Betrag und verfuegbarer Rest stehen am Wallet.
 */
final class PostpaidTerms
{
    /**
     * Laeuft das Verfahren ueberhaupt? Steht der Hauptschalter auf false, zeigt
     * das Portal weder Antrag noch Aufschlag.
     */
    public static function enabled(): bool
    {
        return (bool) config('wallet.postpaid.enabled');
    }

    /**
     * Kauft dieses Wallet gegen Kreditrahmen? Bewusst ohne den Hauptschalter:
     * Ein bereits freigeschalteter Kaeufer bleibt Postpaid, auch wenn der
     * Rollout angehalten wird -- seine offenen Betraege sind dieselben.
     */
    public static function isPostpaid(?Wallet $wallet): bool
    {
        return $wallet?->payment_mode === PaymentMode::POSTPAID;
    }

    /**
     * Der Aufschlagsatz als Zahl, etwa 7.5.
     *
     * Mit Wallet gilt dessen abweichender Satz, falls einer vereinbart ist.
     * Der Kaeufer soll im Portal die Zahl sehen, die ihm berechnet wird, und
     * nicht die Vorgabe der Plattform.
     */
    public static function surchargePercent(?Wallet $wallet = null): float
    {
        return $wallet instanceof Wallet
            ? $wallet->effectiveSurchargePercent()
            : (float) config('wallet.postpaid.surcharge_percent');
    }

    /**
     * Der Aufschlagsatz, wie er im Text steht: "7,5" und nicht "7.5". Ganze
     * Saetze bleiben ohne Nachkommastelle.
     */
    public static function surchargePercentLabel(?Wallet $wallet = null): string
    {
        $percent = self::surchargePercent($wallet);

        return rtrim(rtrim(number_format($percent, 2, ',', '.'), '0'), ',');
    }

    /**
     * Der Hinweis am Preis: "inkl. 7,5 % Pay-as-you-go-Aufschlag".
     */
    public static function surchargeHint(?Wallet $wallet = null): string
    {
        return (string) __('portal.postpaid.balance.surcharge_hint', [
            'percent' => self::surchargePercentLabel($wallet),
        ]);
    }

    /**
     * Derselbe Hinweis zu einem Satz, der bereits feststeht -- etwa dem aus
     * einem Kaufbeleg. Ein bezahlter Preis darf sich nicht aendern, nur weil
     * der Satz des Kaeufers inzwischen ein anderer ist.
     */
    public static function surchargeHintForPercent(float $percent): string
    {
        return (string) __('portal.postpaid.balance.surcharge_hint', [
            'percent' => rtrim(rtrim(number_format($percent, 2, ',', '.'), '0'), ','),
        ]);
    }

    /**
     * Der Kreditrahmen eines frisch freigeschalteten Kaeufers -- die Zahl, die
     * im Antrag genannt wird. Der tatsaechliche Rahmen steht am Wallet.
     */
    public static function defaultCreditLimitCents(): int
    {
        return (int) config('wallet.postpaid.default_credit_limit_cents');
    }

    /**
     * Offener Betrag, ab dem ohne Warten auf den Wochentermin eingezogen wird.
     */
    public static function thresholdCents(): int
    {
        return (int) config('wallet.postpaid.settlement_threshold_cents');
    }

    /**
     * Der Wochentag des regulaeren Einzugs in der Sprache der Oberflaeche.
     *
     * In der Konfiguration steht er englisch und klein, weil der Scheduler ihn
     * so erwartet. Ein Kaeufer liest "Montag".
     */
    public static function settlementWeekday(): string
    {
        $weekday = (string) config('wallet.postpaid.settlement_weekday');

        try {
            return Carbon::parse($weekday)->locale(app()->getLocale())->dayName;
        } catch (\Throwable) {
            return $weekday;
        }
    }

    /**
     * Der Satz, der ueber dem offenen Betrag steht: wann eingezogen wird.
     */
    public static function settlementTooltip(): string
    {
        return (string) __('portal.postpaid.balance.tooltip', [
            'weekday' => self::settlementWeekday(),
            'threshold' => Money::format(self::thresholdCents()),
        ]);
    }
}
