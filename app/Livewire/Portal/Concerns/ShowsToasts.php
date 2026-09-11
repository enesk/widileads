<?php

declare(strict_types=1);

namespace App\Livewire\Portal\Concerns;

/**
 * Kurzmeldungen aus einer Portal-Komponente.
 *
 * Im Filament-Dashboard erledigte das die Notification-Fassade. Das Portal
 * laeuft ohne Filament, deshalb hier: Die Komponente schickt ein
 * Browser-Ereignis, `resources/js/modules/toasts.js` baut daraus den Toast, das
 * Markup steht einmal im Rahmen (`x-app.toasts`).
 *
 * **Wofuer ein Toast und wofuer nicht.** Ein Toast beantwortet "ist es
 * angekommen?" und verschwindet wieder -- gespeichert, verschickt, gestartet.
 * Er ist der falsche Ort fuer alles, was der Benutzer noch braucht: Eine
 * Meldung mit einem Weg heraus ("Guthaben reicht nicht, hier aufladen") gehoert
 * als Band an die Stelle, an der sie gilt, sonst ist der Verweis nach fuenf
 * Sekunden weg. Feldfehler gehoeren ans Feld.
 *
 * Nach einer Weiterleitung gibt es kein Ereignis mehr, an dem sich jemand
 * haengen koennte. Dafuer steht `flash()`: Die Meldung reist in der Sitzung
 * mit und wird auf der Zielseite serverseitig gerendert.
 */
trait ShowsToasts
{
    /**
     * Eine Bestaetigung -- der Normalfall nach dem Speichern.
     */
    public function toast(string $message, ?string $title = null): void
    {
        $this->sendToast($message, 'success', $title);
    }

    /**
     * Es hat geklappt, aber nicht wie erhofft: eine Sperrzeit, eine Grenze, ein
     * Hinweis, den der Benutzer kennen soll.
     */
    public function toastWarning(string $message, ?string $title = null): void
    {
        $this->sendToast($message, 'warning', $title);
    }

    /**
     * Es hat nicht geklappt.
     */
    public function toastError(string $message, ?string $title = null): void
    {
        $this->sendToast($message, 'danger', $title);
    }

    /**
     * Eine Meldung, die eine Weiterleitung ueberleben soll.
     *
     * Ein Ereignis waere hier verloren: Der Browser laedt eine neue Seite, die
     * nichts von der vorigen weiss. Gerendert wird sie von x-app.toasts.
     */
    public function flashToast(string $message, string $level = 'success', ?string $title = null): void
    {
        session()->flash('toast', [
            'message' => $message,
            'level' => $level,
            'title' => $title,
        ]);
    }

    private function sendToast(string $message, string $level, ?string $title): void
    {
        if (trim($message) === '') {
            return;
        }

        $this->dispatch('toast', message: $message, level: $level, title: $title);
    }
}
