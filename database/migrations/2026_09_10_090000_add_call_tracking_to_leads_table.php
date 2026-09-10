<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FB-082: Der Lead bekommt seine Erreichbarkeits-Akte.
 *
 * `lead_state` bleibt die einzige Zustandsspalte des Leads
 * (Architekturleitsatz 1). `contact_status` ist eine andere Achse: Sie sagt
 * nicht, wo der Lead im Verkauf steht, sondern ob der Kaeufer ihn ans Telefon
 * bekommen hat. Beide koennen sich unabhaengig voneinander bewegen -- ein
 * verkaufter Lead ist erst `billable`, wenn jemand ihn erreicht hat.
 *
 * `phone_e164` gibt es seit FB-031, deshalb steht es hier nicht noch einmal.
 *
 * Die Frist haengt am Lead und nicht am Kaufbeleg: Sie laeuft ab Auslieferung
 * und gilt fuer den Lead als Ganzes. Bei einem geteilten Lead (FB-055) beginnt
 * die Uhr mit der ersten Auslieferung; jeder Kaeufer hat dieselbe Frist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // Zeitpunkt, ab dem der Kaeufer anrufen kann -- die Uhr der Frist.
            $table->timestamp('delivered_at')->nullable()->after('settled_at');

            // delivered_at + config('lead_calls.deadline_days'). Als eigene
            // Spalte und nicht gerechnet, damit der Scheduler danach indiziert
            // suchen kann und eine spaetere Aenderung der Frist laufende Faelle
            // nicht rueckwirkend verschiebt.
            $table->timestamp('deadline_at')->nullable()->after('delivered_at');

            // Erlaubte Werte: App\Constants\LeadContactStatus (open, billable,
            // unreachable). Als String aus demselben Grund wie `lead_state`.
            $table->string('contact_status', 20)->default('open')->after('deadline_at');

            $table->timestamp('resolved_at')->nullable()->after('contact_status');

            // Warum entschieden wurde: App\Constants\LeadResolutionReason
            // (answered, three_attempts, deadline). Null = noch offen.
            $table->string('resolved_by', 20)->nullable()->after('resolved_at');

            // Ab wann der Kaeufer die Rufnummer im Klartext sieht. Vorher
            // maskiert (FB-084) -- gewaehlt wird ueber Twilio, nicht von Hand.
            $table->timestamp('phone_revealed_at')->nullable()->after('resolved_by');

            // Der Scheduler sucht offene Faelle mit abgelaufener Frist.
            $table->index(['contact_status', 'deadline_at']);
        });

        $this->backfill();
    }

    /**
     * Bestandsleads einordnen.
     *
     * `contact_status` erledigt der Standardwert. Bleibt die Auslieferung:
     * Sie ist bisher nirgends festgehalten, der Kaufbeleg ist der beste
     * Beleg dafuer. Bei einem geteilten Lead zaehlt der frueheste Kauf.
     * Leads ohne Kauf bleiben ohne Frist -- sie sind noch bei niemandem.
     */
    private function backfill(): void
    {
        $deadlineDays = (int) config('lead_calls.deadline_days', 7);

        DB::table('lead_purchases')
            ->select('lead_id', DB::raw('MIN(purchased_at) as first_purchased_at'))
            ->groupBy('lead_id')
            ->orderBy('lead_id')
            ->chunk(500, function ($rows) use ($deadlineDays) {
                foreach ($rows as $row) {
                    $deliveredAt = Carbon::parse($row->first_purchased_at);

                    DB::table('leads')->where('id', $row->lead_id)->update([
                        'delivered_at' => $deliveredAt,
                        'deadline_at' => $deliveredAt->copy()->addDays($deadlineDays),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['contact_status', 'deadline_at']);

            $table->dropColumn([
                'delivered_at',
                'deadline_at',
                'contact_status',
                'resolved_at',
                'resolved_by',
                'phone_revealed_at',
            ]);
        });
    }
};
