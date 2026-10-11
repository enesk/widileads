<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Constants\PhoneReleaseReason;
use App\Dto\LeadContact;
use App\Dto\PhoneRelease;
use App\Models\Lead;
use App\Models\User;

/**
 * Bereitet einen Lead fuer die Anzeige auf (FB-032).
 *
 * Views bekommen ihre Kontaktdaten ausschliesslich von hier -- und zwar bereits
 * fertig maskiert. In einem Blade-Template steht damit nie eine Entscheidung
 * darueber, was angezeigt werden darf; dort wird nur noch ausgegeben, was
 * dieser Presenter herausgibt.
 */
class LeadPresenter
{
    private readonly LeadContact $contact;

    /**
     * Der Freigabestand der Rufnummer -- erst beim ersten Zugriff geholt.
     *
     * false heisst "noch nicht gefragt", null heisst "es gibt keinen Stand":
     * Der Betrachter hat den Lead nicht gekauft, oder es ist die Teamsicht.
     */
    private PhoneRelease|null|false $release = false;

    /**
     * @param  bool  $teamView  Die Fassung fuer "Team Leads" (Ticket #3):
     *                          Rufnummer und E-Mail bleiben verdeckt, ganz
     *                          unabhaengig vom Betrachter. Bewusst ein
     *                          Schalter und keine fertige LeadContact von
     *                          aussen -- sonst waere der Presenter die Stelle,
     *                          an der jemand Klartext hineinreicht.
     */
    public function __construct(
        public readonly Lead $lead,
        private readonly ?User $viewer,
        bool $teamView = false,
    ) {
        $this->contact = $teamView ? $lead->contactForTeamView() : $lead->contactFor($viewer);

        if ($teamView) {
            // Die Teamsicht zeigt die Nummer niemandem (Ticket #3). Ein
            // Versuchsstand waere dort nur eine Zahl ohne Folge.
            $this->release = null;
        }
    }

    /**
     * Der Lead in der Teamsicht (Ticket #3).
     *
     * Ohne Betrachter, weil es keinen gibt, fuer den diese Fassung anders
     * ausfaellt.
     */
    public static function forTeamView(Lead $lead): self
    {
        return new self($lead, null, teamView: true);
    }

    public function contact(): LeadContact
    {
        return $this->contact;
    }

    public function isContactMasked(): bool
    {
        return $this->contact->masked;
    }

    public function name(): string
    {
        return $this->contact->fullName() ?? __('leads.contact.unknown');
    }

    public function email(): string
    {
        return $this->contact->email ?? __('leads.contact.missing');
    }

    public function phone(): string
    {
        return $this->contact->phone ?? __('leads.contact.missing');
    }

    /**
     * Ist die Rufnummer verkuerzt? (FB-085)
     *
     * Kann auch dann true sein, wenn die uebrigen Kontaktdaten im Klartext
     * stehen: Ein Kaeufer sieht Name, E-Mail und Postleitzahl sofort nach dem
     * Kauf, die Rufnummer aber erst nach der Abrechnung.
     */
    public function isPhoneMasked(): bool
    {
        return $this->contact->phoneMasked;
    }

    /**
     * Warum die Rufnummer verkuerzt ist -- oder null, wenn sie vollstaendig
     * dasteht (FB-085).
     *
     * Bei vollstaendig verdeckten Kontaktdaten sagt bereits der allgemeine
     * Hinweis alles; ein zweiter Satz nur zur Rufnummer waere dort Laerm.
     */
    public function phoneHint(): ?string
    {
        if (! $this->contact->phoneMasked || $this->contact->masked) {
            return null;
        }

        $release = $this->phoneRelease();

        if ($release === null) {
            return null;
        }

        if ($release->reason === PhoneReleaseReason::REVOKED_CREDIT) {
            return __('call.phone_release.unreachable');
        }

        return __('call.phone_release.pending', [
            'count' => $release->countedAttempts,
            'required' => $release->requiredAttempts,
        ]);
    }

    /**
     * Der Freigabestand der Rufnummer -- oder null (Ticket #5).
     *
     * Dieselbe Rechnung, die ueber die Maskierung entscheidet. Views zeigen
     * den Versuchsstand ausschliesslich hieraus an und zaehlen nicht selbst;
     * sonst steht im Hinweis eine andere Zahl als die, die entscheidet.
     */
    public function phoneRelease(): ?PhoneRelease
    {
        if ($this->release === false) {
            $this->release = $this->lead->phoneReleaseForViewer($this->viewer);
        }

        return $this->release;
    }

    public function postalCode(): string
    {
        return $this->contact->postalCode ?? __('leads.contact.missing');
    }
}
