<?php

declare(strict_types=1);

namespace App\Presenters;

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

    public function postalCode(): string
    {
        return $this->contact->postalCode ?? __('leads.contact.missing');
    }
}
