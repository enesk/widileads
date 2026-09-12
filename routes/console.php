<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Schedule::command('app:generate-sitemap')->everyOddHour();

Schedule::command('app:metrics-beat')->dailyAt('00:01');

Schedule::command('app:local-subscription-expiring-soon-reminder')->dailyAt('00:01');

Schedule::command('app:cleanup-local-subscription-statuses')->hourly();

Schedule::command('app:sync-seat-based-subscription-quantities')->hourly();

// FB-037: Aufbewahrungsfrist der Leads. Uhrzeit aus config/funnel.php.
Schedule::command('app:apply-lead-retention')->dailyAt(config('funnel.lead.retention_run_at'));

// FB-021: Liegengebliebene Funnel-Sitzungen als abgebrochen markieren.
// Frist aus config/funnel.php (public.abandon_after_minutes).
Schedule::command('app:abandon-stale-funnel-sessions')->everyFiveMinutes();

// FB-054: Abgelaufene Lead-Reservierungen zurueckgeben. Die Frist steht in
// config('funnel.lead.reservation_ttl'); haeufiger zu pruefen als die Frist
// lang ist, waere Verschwendung -- spaeter als eine Minute nach Ablauf
// freizugeben, aergert dagegen den naechsten Kaeufer.
Schedule::command('app:release-expired-lead-reservations')->everyMinute();

// FB-056: Autokauf fuer Kaeufer, die ihn eingeschaltet haben. Haeufiger als
// noetig zu laufen kostet nichts -- passt kein Lead, endet der Lauf sofort.
// Zu selten zu laufen kostet dagegen Abschluesse: Ein frischer Lead ist nach
// Stunden deutlich weniger wert.
Schedule::command('app:auto-purchase-leads')->everyFiveMinutes()->withoutOverlapping();

// FB-058: Kaeufe abschliessen, deren Reklamationsfrist abgelaufen ist. Die
// Frist steht in config('funnel.call.deadline_days'); taeglich zu pruefen
// genuegt, sie zaehlt in Tagen.
Schedule::command('app:settle-elapsed-complaint-periods')->dailyAt('01:00');

// FB-084 (Ticket #11): Leads abschliessen, deren Frist zur
// Erreichbarkeitspruefung abgelaufen ist. Taeglich um 03:00 Ortszeit -- die
// Frist zaehlt in Tagen, nachts stoert der Lauf niemanden. withoutOverlapping
// und onOneServer, weil jeder abgeschlossene Lead eine Abrechnung ausloest.
Schedule::command('leads:resolve-expired')
    ->dailyAt('03:00')
    ->timezone('Europe/Berlin')
    ->withoutOverlapping()
    ->onOneServer();

// FB-084 (Ticket #14): Kaeufer erinnern, bevor die Frist zur
// Erreichbarkeitspruefung ablaeuft. Stuendlich, weil die Erinnerung 24 Stunden
// vor Fristende hinausgehen soll und die Fristen ueber den Tag verteilt
// ablaufen. withoutOverlapping und onOneServer, damit derselbe Lead nicht aus
// zwei Laeufen zugleich erinnert wird.
Schedule::command('leads:send-deadline-reminders')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

// LP-WALLET-015: Taegliche Konsistenzpruefung des Wallet-Ledgers. Um 04:00
// Ortszeit, wenn kein Kauf laeuft -- ein Lauf waehrend laufender Buchungen
// meldet sonst Abweichungen, die im naechsten Moment keine mehr sind.
// onOneServer, weil zwei parallele Laeufe dieselbe Abweichung zweimal melden
// wuerden. Ohne --repair: Ein abgewichener Saldo wird gemeldet, nicht
// stillschweigend geheilt.
Schedule::command('wallet:verify')
    ->dailyAt('04:00')
    ->timezone('Europe/Berlin')
    ->withoutOverlapping()
    ->onOneServer();

// LP-POSTPAID-008: Woechentlicher Einzug der offenen Postpaid-Betraege.
// Wochentag und Uhrzeit stehen in config('wallet.postpaid.*'); Europe/Berlin,
// weil der Termin dem Kaeufer als Wochentag angekuendigt wird und eine
// Verschiebung durch die Sommerzeit ihn unnoetig verwirren wuerde.
// onOneServer, weil zwei parallele Laeufe denselben offenen Betrag zweimal
// einziehen wuerden.
Schedule::command('wallet:settle')
    ->weeklyOn(
        (int) array_search(
            strtolower((string) config('wallet.postpaid.settlement_weekday', 'monday')),
            ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],
            true,
        ),
        (string) config('wallet.postpaid.settlement_time', '06:00'),
    )
    ->timezone('Europe/Berlin')
    ->withoutOverlapping()
    ->onOneServer();

// LP-POSTPAID-008: Nachlauf, der nur belastet und keine neuen Forderungen
// anlegt. Stuendlich, weil zwei Fristen ueber den Tag verteilt ablaufen: der
// zweite Einzugsversuch nach einem Fehlschlag und das angekuendigte
// Belastungsdatum einer Lastschrift (LP-POSTPAID-014). Eine am Montag
// angekuendigte Lastschrift wird so am Dienstag belastet und nicht erst am
// naechsten Wochentermin.
Schedule::command('wallet:settle --charge-only')
    ->hourly()
    ->timezone('Europe/Berlin')
    ->withoutOverlapping()
    ->onOneServer();
