<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Constants\LeadContactStatus;
use App\Dto\LeadContact;
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

    public function __construct(
        public readonly Lead $lead,
        private readonly ?User $viewer,
    ) {
        $this->contact = $lead->contactFor($viewer);
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

        return $this->lead->contact_status === LeadContactStatus::UNREACHABLE
            ? __('call.phone_release.unreachable')
            : __('call.phone_release.pending');
    }

    public function postalCode(): string
    {
        return $this->contact->postalCode ?? __('leads.contact.missing');
    }
}
