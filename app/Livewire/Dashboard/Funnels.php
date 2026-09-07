<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Actions\ArchiveFunnel;
use App\Actions\CreateFunnelPreviewLink;
use App\Actions\DuplicateFunnel;
use App\Actions\PublishFunnel;
use App\Constants\FunnelStatus;
use App\Exceptions\FunnelNotPublishableException;
use App\Filament\Dashboard\Pages\FunnelBuilder;
use App\Models\Funnel;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Uebersicht der Funnels eines Betreibers (FB-028).
 *
 * Bis hierher gab es keinen Weg zum Builder: Die Seiten aus FB-015, FB-016 und
 * FB-017 haengen alle an einem konkreten Funnel und melden sich deshalb nicht
 * zur Navigation an. Wer den Builder oeffnen wollte, musste die Adresse kennen.
 * Diese Liste ist der Einstieg.
 *
 * Sie baut nichts nach: Veroeffentlichen, Duplizieren, Archivieren und der
 * Vorschau-Link kommen aus den Aktionen von FB-014 und FB-018.
 */
class Funnels extends Component
{
    public string $newName = '';

    /**
     * Gruende, warum ein Funnel nicht veroeffentlicht werden konnte - je
     * Funnel-Kennung, damit sie an der richtigen Zeile stehen.
     *
     * @var array<int, list<string>>
     */
    public array $publishProblems = [];

    /**
     * Zuletzt erzeugter Vorschau-Link. Er ist befristet und signiert, deshalb
     * wird er nicht gespeichert, sondern nur einmal angezeigt.
     */
    public ?string $previewLink = null;

    public function create(): void
    {
        $this->validate(['newName' => ['required', 'string', 'max:255']]);

        $tenant = $this->tenant();

        $funnel = $tenant->funnels()->create([
            'name' => $this->newName,
            'slug' => $this->freeSlug($tenant, $this->newName),
            'status' => FunnelStatus::DRAFT,
        ]);

        $this->reset('newName');

        $this->redirect(
            FunnelBuilder::getUrl(['funnel' => $funnel], panel: 'dashboard', tenant: $tenant),
            navigate: true,
        );
    }

    public function publish(int $funnelId, PublishFunnel $publish): void
    {
        $funnel = $this->funnels()->findOrFail($funnelId);

        try {
            $publish->handle($funnel, auth()->user());
            unset($this->publishProblems[$funnelId]);
        } catch (FunnelNotPublishableException $exception) {
            // Die Gruende kommen fertig formuliert aus FB-014 und werden
            // unveraendert angezeigt - sie sagen genau, was noch fehlt.
            $this->publishProblems[$funnelId] = $exception->reasons;
        }
    }

    public function duplicate(int $funnelId, DuplicateFunnel $duplicate): void
    {
        $duplicate->handle($this->funnels()->findOrFail($funnelId));
    }

    public function archive(int $funnelId, ArchiveFunnel $archive): void
    {
        $archive->handle($this->funnels()->findOrFail($funnelId));
    }

    public function previewLink(int $funnelId, CreateFunnelPreviewLink $links): void
    {
        $this->previewLink = $links->handle($this->funnels()->findOrFail($funnelId));
    }

    public function dismissPreviewLink(): void
    {
        $this->previewLink = null;
    }

    public function render(): View
    {
        return view('livewire.dashboard.funnels', [
            'funnels' => $this->funnels()->withCount('steps', 'questions')->latest()->get(),
            'tenant' => $this->tenant(),
        ]);
    }

    /**
     * @return HasMany<Funnel, Tenant>
     */
    private function funnels()
    {
        return $this->tenant()->funnels();
    }

    /**
     * Ein je Mandant eindeutiger Slug. Der Unique-Index laeuft ueber
     * (tenant_id, slug); ein Duplikat waere ein Fehler beim Anlegen.
     */
    private function freeSlug(Tenant $tenant, string $name): string
    {
        $base = Str::slug($name) ?: 'funnel';
        $taken = $tenant->funnels()->pluck('slug')->all();

        $candidate = $base;
        $suffix = 1;

        while (in_array($candidate, $taken, true)) {
            $candidate = $base.'-'.(++$suffix);
        }

        return $candidate;
    }

    private function tenant(): Tenant
    {
        $tenant = Filament::getTenant();

        abort_unless($tenant instanceof Tenant, 403);

        return $tenant;
    }
}
