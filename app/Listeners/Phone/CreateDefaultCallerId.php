<?php

declare(strict_types=1);

namespace App\Listeners\Phone;

use App\Constants\TenantType;
use App\Events\Phone\PhoneVerified;
use App\Models\Tenant;
use App\Services\CallerIdService;

/**
 * Legt die bestaetigte Nummer als Rufnummernanzeige des Kaeufers an
 * (LP-ONBOARD-004).
 *
 * Der Nachweis ist derselbe: Twilio Verify hat gerade bewiesen, dass der
 * Nutzer die Nummer in der Hand hat. Ihn zusaetzlich den Bestaetigungsanruf
 * von FB-080 durchlaufen zu lassen, waere ein zweiter Nachweis fuer dieselbe
 * Tatsache -- und ein weiterer Grund, vor dem ersten Lead abzubrechen.
 *
 * Was mit einer bereits vorhandenen Zeile passiert, entscheidet der Dienst und
 * nicht dieser Zuhoerer (Ticket #49): Eine unbrauchbare Zeile mit derselben
 * Nummer wird erneuert, jede andere bleibt stehen. Deshalb steht hier kein
 * Vorab-Riegel mehr -- zwei Stellen mit derselben Regel liefen auseinander.
 *
 * Angelegt wird in seinem Kaeufer-Workspace: Nur dort wird telefoniert.
 * Verkaeufer-Workspaces bekommen keine.
 */
class CreateDefaultCallerId
{
    public function __construct(private readonly CallerIdService $callerIds) {}

    public function handle(PhoneVerified $event): void
    {
        $user = $event->user;

        if ($user->phone_e164 === null || $user->phone_e164 === '') {
            return;
        }

        $workspace = $user->tenants()
            ->where('type', TenantType::BUYER)
            ->orderBy('tenants.id')
            ->first();

        if (! $workspace instanceof Tenant) {
            return;
        }

        $this->callerIds->adoptVerifiedNumber($workspace, $user, $user->phone_e164);
    }
}
