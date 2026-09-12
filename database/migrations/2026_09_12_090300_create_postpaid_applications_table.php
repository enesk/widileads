<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LP-POSTPAID-003: Antrag auf Freischaltung von Pay as you go.
 *
 * Der Antrag ist der Beleg einer Entscheidung und nicht nur ein Schalter:
 * `eligibility_snapshot` haelt fest, welche Zahlen zum Zeitpunkt der
 * Antragstellung galten -- abgerechnete Kaeufe, Kontoalter, Zahlungshistorie
 * (LP-POSTPAID-006). Ohne diesen Schnappschuss liesse sich eine spaetere
 * Ablehnung oder ein Zahlungsausfall nicht mehr nachvollziehen, weil die Werte
 * bis dahin weitergelaufen sind.
 *
 * Wie Zahlungsmittel und Settlements haengt der Antrag am Wallet: Der
 * Kreditrahmen, um den es geht, steht dort.
 *
 * Bewusst kein Unique-Index auf `wallet_id`: Ein abgelehnter Kaeufer darf
 * spaeter erneut beantragen, die Historie bleibt dabei erhalten. Dass nur ein
 * Antrag gleichzeitig offen ist, prueft der PostpaidService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postpaid_applications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();

            // Erlaubte Werte: requested | approved | rejected. Als String, wie
            // ueberall im Funnel Builder.
            $table->string('status', 20)->default('requested');

            // Die Eignungszahlen zum Zeitpunkt der Antragstellung.
            $table->json('eligibility_snapshot');

            $table->timestamp('requested_at');

            // Gesetzt, sobald der Admin entschieden hat. nullOnDelete, damit
            // der Beleg ein geloeschtes Adminkonto ueberlebt.
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();

            // Begruendung der Entscheidung, dem Kaeufer sichtbar.
            $table->text('note')->nullable();

            $table->timestamps();

            // Die Arbeitsliste des Admins: offene Antraege, aelteste zuerst.
            $table->index(['status', 'requested_at']);

            // Die Historie eines Kaeufers, neueste zuerst.
            $table->index(['wallet_id', 'requested_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postpaid_applications');
    }
};
