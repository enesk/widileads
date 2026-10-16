<?php

namespace App\Livewire\Announcement;

use App\Constants\AnnouncementPlacement;
use App\Constants\SessionConstants;
use App\Services\AnnouncementService;
use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
class View extends Component
{
    public ?string $placement;

    /**
     * Gestaltung des Bands. Wahr: das Band des Portals (Startseite und
     * angemeldeter Arbeitsbereich). Falsch: der Kasten des Starterkits, der
     * noch im SaaSykit-Kopf von Blog und Roadmap haengt. Zwei Ansichten, eine
     * Logik -- Zeitfenster, Aktivschaltung und Platzierung entscheidet in
     * beiden Faellen der AnnouncementService.
     */
    public bool $portal = false;

    /**
     * Flaeche und Polsterung des Bands kommen von der einbindenden Seite: Auf
     * den oeffentlichen Seiten eine randlose Flaeche ueber die ganze Breite mit
     * der Polsterung im Container, im Arbeitsbereich eine gerundete Karte im
     * Inhaltsbereich.
     */
    public string $wrapperClass = '';

    public string $innerClass = '';

    /**
     * CSS-Auswahl des Elements, das nach dem Schliessen den Fokus bekommt --
     * sonst landet die Tastatur am Dokumentanfang. Leer: kein Fokuswechsel.
     */
    public string $focusAfterDismiss = '';

    public function mount(?string $placement = null, bool $portal = false, string $wrapperClass = '', string $innerClass = '', string $focusAfterDismiss = '')
    {
        $this->placement = $placement;
        $this->portal = $portal;
        $this->wrapperClass = $wrapperClass;
        $this->innerClass = $innerClass;
        $this->focusAfterDismiss = $focusAfterDismiss;
    }

    public function dismiss($id)
    {
        if (session()->exists(SessionConstants::DISMISSED_ANNOUNCEMENTS)) {
            $dismissedAnnouncements = session()->get(SessionConstants::DISMISSED_ANNOUNCEMENTS);
        } else {
            $dismissedAnnouncements = [];
        }

        if (! in_array($id, $dismissedAnnouncements)) {
            $dismissedAnnouncements[] = $id;
        }

        session()->put(SessionConstants::DISMISSED_ANNOUNCEMENTS, $dismissedAnnouncements);

        $this->skipRender();
    }

    public function render(AnnouncementService $announcementService)
    {
        $placement = $this->placement ? AnnouncementPlacement::tryFrom($this->placement) : AnnouncementPlacement::FRONTEND;

        return view(
            $this->portal ? 'livewire.announcement.portal-band' : 'livewire.announcement.view', [
                'announcement' => $announcementService->getAnnouncement($placement),
            ]
        );
    }
}
