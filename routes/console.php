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

// FB-058: Kaeufe abschliessen, deren Reklamationsfrist abgelaufen ist. Die
// Frist steht in config('funnel.call.deadline_days'); taeglich zu pruefen
// genuegt, sie zaehlt in Tagen.
Schedule::command('app:settle-elapsed-complaint-periods')->dailyAt('01:00');
