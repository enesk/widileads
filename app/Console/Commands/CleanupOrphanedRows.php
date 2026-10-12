<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Bereinigt verwaiste Zeilen, die der Live-Stand aus der Zeit ohne
 * Fremdschluessel mitschleppt (Ticket #87, Befund aus #83).
 *
 * Der Live-Stand trug am 2026-10-12 alle Migrationen als gelaufen, hatte aber
 * nur 24 der 153 Fremdschluessel. In dieser Zeit sind Mandanten, Nutzer,
 * Funnels und Leads geloescht worden, ohne dass ihre Kinder mitgingen. Deshalb
 * bleiben 21 Beziehungen offen, wenn
 * 2026_10_12_130000_restore_missing_foreign_keys.php die Fremdschluessel
 * nachzieht: Die Datenbank wuerde die Zeilen beim Anlegen zurueckweisen.
 *
 * **Die Regel dieses Laufs: angewandt wird genau das, was der fehlende
 * Fremdschluessel selbst vorschreibt.** `cascade` heisst, die Zeile waere beim
 * Loeschen des Elternteils mitgegangen -- sie wird geloescht. `set null` heisst,
 * der Verweis waere geleert worden -- die Spalte wird auf NULL gesetzt. Nichts
 * davon ist eine neue Entscheidung; es ist die Regel, die die Migration ohnehin
 * festlegt, nur nachgeholt. Der Lauf erfindet damit keine Fachlogik und ist
 * fuer jede Gruppe aus der Soll-Liste nachpruefbar.
 *
 * Nur die beiden `restrict`-Beziehungen der Geldseite schreiben nichts vor --
 * `restrict` heisst gerade, dass das Loeschen haette scheitern muessen. Dort
 * entscheidet dieser Lauf von Hand, und zwar ohne einen Beleg zu loeschen:
 *
 * - `transactions.tenant_id` wird aus `orders.tenant_id` des eigenen Belegs
 *   richtiggestellt. Live sind das drei Zeilen auf Mandant 9, deren
 *   Bestellungen alle auf Mandant 2 lauten -- der Wert ist also herleitbar und
 *   nicht geraten. Damit bleibt die Geldseite vollstaendig und die Spalte muss
 *   nicht nullable werden.
 * - `user_stripe_data.tenant_id` geht auf NULL. Die Spalte ist nullable, die
 *   Zeile bleibt als Beleg des Stripe-Kunden stehen, aber kein Mandant
 *   beansprucht sie mehr. Umhaengen waere hier falsch: PaymentMethodService
 *   liest `where tenant_id = ...` mit `first()` und koennte danach den alten
 *   Kunden statt des aktuellen erwischen.
 *
 * Nicht geloescht wird in jedem Fall ein Lead mit Kaufbeleg: `lead_purchases`
 * ist `restrict`, ein verkaufter Lead ist Beleg und kein Muell. Solche Leads
 * bleiben stehen und werden gemeldet.
 *
 * Ohne `--apply` schreibt der Lauf nichts: Er fuehrt dieselbe Folge in einer
 * Transaktion aus und dreht sie am Ende zurueck. Die gemeldeten Zahlen sind
 * damit die echten, nicht geschaetzte.
 */
class CleanupOrphanedRows extends Command
{
    /**
     * Hoechstzahl von Werten in einem `whereIn`. Die Mengen sind klein, der
     * Deckel haelt die Abfrage trotzdem lesbar lang.
     */
    private const CHUNK_SIZE = 500;

    protected $signature = 'db:cleanup-orphans
        {--apply : Aenderungen wirklich schreiben (ohne die Option laeuft alles als Probe)}
        {--force : Rueckfrage vor dem Schreiben ueberspringen}';

    protected $description = 'Bereinigt verwaiste Zeilen aus der Zeit ohne Fremdschluessel (Ticket #87).';

    /**
     * Was der Lauf angefasst hat, in der Reihenfolge der Schritte.
     *
     * @var list<array{0: string, 1: string, 2: int}>
     */
    private array $touched = [];

    /**
     * Befunde, die dieser Lauf nicht aufloest -- gemeldet, nicht geloescht.
     *
     * @var list<string>
     */
    private array $problems = [];

    public function handle(): int
    {
        $apply = $this->confirmedApply();

        if ($apply === null) {
            return self::FAILURE;
        }

        // Von Hand statt DB::transaction(): Die Probe laeuft dieselbe Folge und
        // dreht sie am Ende zurueck -- ein Rollback aus dem Closure heraus
        // wuerde den Commit danach ins Leere laufen lassen.
        DB::beginTransaction();

        try {
            $this->cleanMoneySide();
            $this->cleanAssignments();
            $this->cleanLogsAndLeftovers();
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        if ($apply) {
            DB::commit();
        } else {
            DB::rollBack();
        }

        $this->report($apply);

        return $this->problems === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Gruppe 1: Geldseite. Hier wird nichts geloescht.
     */
    private function cleanMoneySide(): void
    {
        $this->line('Gruppe 1: Geldseite');

        $orphans = DB::table('transactions')
            ->whereNotExists(fn ($query) => $query->from('tenants')->whereColumn('tenants.id', 'transactions.tenant_id'))
            ->get(['id', 'order_id', 'tenant_id']);

        $corrected = 0;

        foreach ($orphans as $transaction) {
            $orderTenantId = $transaction->order_id === null ? null : DB::table('orders')
                ->where('id', $transaction->order_id)
                ->whereExists(fn ($query) => $query->from('tenants')->whereColumn('tenants.id', 'orders.tenant_id'))
                ->value('tenant_id');

            if ($orderTenantId === null) {
                $this->problems[] = sprintf(
                    'transactions #%d zeigt auf Mandant %d, der fehlt, und hat keine Bestellung mit gueltigem Mandanten '
                    .'-- der richtige Wert ist nicht herleitbar. Zeile bleibt unberuehrt.',
                    (int) $transaction->id,
                    (int) $transaction->tenant_id,
                );

                continue;
            }

            DB::table('transactions')->where('id', $transaction->id)->update(['tenant_id' => $orderTenantId]);
            $corrected++;
        }

        $this->record('transactions.tenant_id', 'aus der Bestellung richtiggestellt', $corrected);

        $this->record(
            'user_stripe_data.tenant_id',
            'auf NULL gesetzt (Zeile bleibt als Beleg)',
            DB::table('user_stripe_data')
                ->whereNotNull('tenant_id')
                ->whereNotExists(fn ($query) => $query->from('tenants')->whereColumn('tenants.id', 'user_stripe_data.tenant_id'))
                ->update(['tenant_id' => null]),
        );
    }

    /**
     * Gruppe 2: Zuordnungen zu geloeschten Mandanten und Nutzern.
     *
     * Alle betroffenen Beziehungen sind `cascade` -- die Zeilen waeren mit dem
     * Mandanten gegangen, haette der Fremdschluessel bestanden.
     */
    private function cleanAssignments(): void
    {
        $this->line('Gruppe 2: Zuordnung');

        $this->purgeLeadsOfMissingTenants();

        $tenantUserIds = DB::table('tenant_user')
            ->where(fn ($query) => $query
                ->whereNotExists(fn ($sub) => $sub->from('tenants')->whereColumn('tenants.id', 'tenant_user.tenant_id'))
                ->orWhereNotExists(fn ($sub) => $sub->from('users')->whereColumn('users.id', 'tenant_user.user_id')))
            ->pluck('id')
            ->all();

        $this->record('team_tenant_user.tenant_user_id', 'geloescht (Kind der Mitgliedschaft)', $this->deleteIn('team_tenant_user', 'tenant_user_id', $tenantUserIds));
        $this->record('tenant_user', 'geloescht (Mandant oder Nutzer fehlt)', $this->deleteIn('tenant_user', 'id', $tenantUserIds));

        foreach (['buyer_profiles', 'buyer_registrations'] as $table) {
            $this->record($table.'.tenant_id', 'geloescht (Mandant fehlt)', $this->deleteOrphans($table, 'tenant_id', 'tenants'));
        }
    }

    /**
     * Die Leads geloeschter Mandanten samt ihrer Kinder.
     *
     * `leads.tenant_id` ist `cascade` und nicht nullable -- es gibt also keinen
     * dritten Weg: Entweder haengen die Leads an einem Mandanten oder sie sind
     * weg. Live sind das 205 Leads der Demo-Mandanten 6 und 10 aus dem
     * PortalDemoSeeder (Kontaktadressen auf `@demo.test`), gestellt von dem
     * ebenfalls geloeschten Funnel 3. Kein einziger hat einen Kaufbeleg.
     */
    private function purgeLeadsOfMissingTenants(): void
    {
        $leadIds = DB::table('leads')
            ->whereNotExists(fn ($query) => $query->from('tenants')->whereColumn('tenants.id', 'leads.tenant_id'))
            ->pluck('id')
            ->all();

        if ($leadIds === []) {
            $this->record('leads.tenant_id', 'geloescht (Mandant fehlt)', 0);

            return;
        }

        $sold = $this->valuesIn('lead_purchases', 'lead_id', $leadIds);

        if ($sold !== []) {
            $this->problems[] = sprintf(
                '%d Lead(s) eines fehlenden Mandanten haben einen Kaufbeleg und bleiben deshalb stehen '
                .'(lead_purchases ist restrict): %s. Der Fremdschluessel leads.tenant_id bleibt damit offen; '
                .'wer den Mandanten los wird, muss den Kauf erst ueber den Fachweg abwickeln.',
                count($sold),
                implode(', ', array_slice($sold, 0, 20)),
            );

            $leadIds = array_values(array_diff($leadIds, $sold));
        }

        if ($leadIds === []) {
            $this->record('leads.tenant_id', 'geloescht (Mandant fehlt)', 0);

            return;
        }

        // Ueberlebende Leads, die auf einen zu loeschenden Lead als Duplikat
        // zeigen: `set null`, nicht mitloeschen.
        $this->record('leads.duplicate_of_lead_id', 'auf NULL gesetzt (Original wird geloescht)', $this->nullifyIn('leads', 'duplicate_of_lead_id', $leadIds));

        foreach (['lead_answers', 'lead_state_log', 'call_attempts', 'lead_complaints', 'lead_watchlist'] as $table) {
            $this->record($table.'.lead_id', 'geloescht (Kind des Leads)', $this->deleteIn($table, 'lead_id', $leadIds));
        }

        $this->record('leads.tenant_id', 'geloescht (Mandant fehlt)', $this->deleteIn('leads', 'id', $leadIds));
    }

    /**
     * Gruppe 3: Protokoll und Beiwerk.
     *
     * `set null` wird geleert, `cascade` geloescht -- siehe Kopfkommentar.
     */
    private function cleanLogsAndLeftovers(): void
    {
        $this->line('Gruppe 3: Protokoll und Beiwerk');

        $this->record('audit_logs.tenant_id', 'auf NULL gesetzt', $this->nullifyOrphans('audit_logs', 'tenant_id', 'tenants'));
        $this->record('lead_state_log.actor_id', 'auf NULL gesetzt', $this->nullifyOrphans('lead_state_log', 'actor_id', 'users'));
        $this->record('funnel_versions.published_by', 'auf NULL gesetzt', $this->nullifyOrphans('funnel_versions', 'published_by', 'users'));

        foreach (['lead_answers', 'lead_state_log', 'call_attempts', 'lead_complaints', 'lead_watchlist'] as $table) {
            $this->record($table.'.lead_id', 'geloescht (Lead fehlt)', $this->deleteOrphans($table, 'lead_id', 'leads'));
        }

        $this->record('leads.duplicate_of_lead_id', 'auf NULL gesetzt (Original fehlt)', $this->nullifyOrphans('leads', 'duplicate_of_lead_id', 'leads'));
        $this->record('leads.reserved_by', 'auf NULL gesetzt (Mandant fehlt)', $this->nullifyOrphans('leads', 'reserved_by', 'tenants'));
        $this->record('leads.funnel_id', 'auf NULL gesetzt (Funnel fehlt)', $this->nullifyOrphans('leads', 'funnel_id', 'funnels'));

        $this->purgeFunnelLeftovers();

        $this->record('leads.funnel_version_id', 'auf NULL gesetzt (Version fehlt)', $this->nullifyOrphans('leads', 'funnel_version_id', 'funnel_versions'));
        $this->record('leads.public_session_id', 'auf NULL gesetzt (Sitzung fehlt)', $this->nullifyOrphans('leads', 'public_session_id', 'public_sessions'));
    }

    /**
     * Die Reste geloeschter Funnels.
     *
     * Alles unter einem Funnel ist `cascade`; gegangen waere es mit dem Funnel.
     * Die Reihenfolge laeuft von innen nach aussen, damit der Lauf auch dann
     * sauber ist, wenn die Kaskaden der Datenbank noch fehlen -- und nichts
     * kaputt macht, wenn sie schon stehen.
     */
    private function purgeFunnelLeftovers(): void
    {
        $versionIds = DB::table('funnel_versions')
            ->whereNotExists(fn ($query) => $query->from('funnels')->whereColumn('funnels.id', 'funnel_versions.funnel_id'))
            ->pluck('id')
            ->all();

        $sessionIds = $this->valuesIn('public_sessions', 'id', $versionIds, 'funnel_version_id');

        $this->record('leads.public_session_id', 'auf NULL gesetzt (Sitzung wird geloescht)', $this->nullifyIn('leads', 'public_session_id', $sessionIds));
        $this->record('session_events.session_id', 'geloescht (Sitzung wird geloescht)', $this->deleteIn('session_events', 'session_id', $sessionIds));
        $this->record('public_sessions.funnel_version_id', 'geloescht (Version des fehlenden Funnels)', $this->deleteIn('public_sessions', 'id', $sessionIds));

        $this->record('leads.funnel_version_id', 'auf NULL gesetzt (Version wird geloescht)', $this->nullifyIn('leads', 'funnel_version_id', $versionIds));
        $this->record('funnels.current_version_id', 'auf NULL gesetzt (Version wird geloescht)', $this->nullifyIn('funnels', 'current_version_id', $versionIds));
        $this->record('funnel_versions.funnel_id', 'geloescht (Funnel fehlt)', $this->deleteIn('funnel_versions', 'id', $versionIds));

        $webhookIds = DB::table('funnel_webhooks')
            ->whereNotExists(fn ($query) => $query->from('funnels')->whereColumn('funnels.id', 'funnel_webhooks.funnel_id'))
            ->pluck('id')
            ->all();

        $this->record('webhook_deliveries.webhook_id', 'geloescht (Webhook wird geloescht)', $this->deleteIn('webhook_deliveries', 'webhook_id', $webhookIds));
        $this->record('funnel_webhooks.funnel_id', 'geloescht (Funnel fehlt)', $this->deleteIn('funnel_webhooks', 'id', $webhookIds));

        $questionIds = DB::table('funnel_questions')
            ->whereNotExists(fn ($query) => $query->from('funnels')->whereColumn('funnels.id', 'funnel_questions.funnel_id'))
            ->pluck('id')
            ->all();

        $this->record('funnel_options.question_id', 'geloescht (Frage wird geloescht)', $this->deleteIn('funnel_options', 'question_id', $questionIds));
        $this->record('funnel_conditions.source_question_id', 'geloescht (Frage wird geloescht)', $this->deleteIn('funnel_conditions', 'source_question_id', $questionIds));
        $this->record('funnel_questions.funnel_id', 'geloescht (Funnel fehlt)', $this->deleteIn('funnel_questions', 'id', $questionIds));

        $stepIds = DB::table('funnel_steps')
            ->whereNotExists(fn ($query) => $query->from('funnels')->whereColumn('funnels.id', 'funnel_steps.funnel_id'))
            ->pluck('id')
            ->all();

        $this->record('funnel_conditions.target_step_id', 'geloescht (Schritt wird geloescht)', $this->deleteIn('funnel_conditions', 'target_step_id', $stepIds));
        $this->record('funnel_questions.step_id', 'geloescht (Schritt wird geloescht)', $this->deleteIn('funnel_questions', 'step_id', $stepIds));
        $this->record('funnel_steps.funnel_id', 'geloescht (Funnel fehlt)', $this->deleteIn('funnel_steps', 'id', $stepIds));

        foreach (['funnel_conditions', 'funnel_results', 'funnel_themes', 'funnel_origins'] as $table) {
            $this->record($table.'.funnel_id', 'geloescht (Funnel fehlt)', $this->deleteOrphans($table, 'funnel_id', 'funnels'));
        }
    }

    /**
     * Loescht alle Zeilen, deren Verweis ins Leere zeigt.
     */
    private function deleteOrphans(string $table, string $column, string $parentTable): int
    {
        return DB::table($table)
            ->whereNotNull($column)
            ->whereNotExists($this->missingParent($table, $column, $parentTable))
            ->delete();
    }

    /**
     * Leert alle Verweise, die ins Leere zeigen.
     *
     * Zeigt die Spalte auf dieselbe Tabelle (`leads.duplicate_of_lead_id`),
     * kann MySQL sie nicht in einer Unterabfrage derselben Aenderung lesen
     * (Fehler 1093) -- deshalb laeuft der Fall in zwei Schritten ueber die
     * Schluesselmenge.
     */
    private function nullifyOrphans(string $table, string $column, string $parentTable): int
    {
        if ($parentTable === $table) {
            $orphans = DB::table($table)
                ->whereNotNull($column)
                ->whereNotExists($this->missingParent($table, $column, $parentTable))
                ->pluck($column)
                ->unique()
                ->values()
                ->all();

            return $this->nullifyIn($table, $column, $orphans);
        }

        return DB::table($table)
            ->whereNotNull($column)
            ->whereNotExists($this->missingParent($table, $column, $parentTable))
            ->update([$column => null]);
    }

    /**
     * Bedingung "das Elternteil fehlt" fuer `whereNotExists`.
     *
     * Das Elternteil wird benannt (`as parent`), weil bei einem Selbstbezug --
     * `leads.duplicate_of_lead_id` zeigt auf `leads.id` -- sonst beide Spalten
     * der Unterabfrage auf dieselbe innere Tabelle aufloesen. Die Bedingung
     * fragte dann "gibt es einen Lead, der sein eigenes Duplikat ist", und jede
     * Zeile zaehlte als verwaist.
     */
    private function missingParent(string $table, string $column, string $parentTable): callable
    {
        return fn ($query) => $query
            ->from($parentTable, 'parent')
            ->whereColumn('parent.id', $table.'.'.$column);
    }

    /**
     * @param  list<int|string>  $values
     */
    private function deleteIn(string $table, string $column, array $values): int
    {
        $deleted = 0;

        foreach (array_chunk($values, self::CHUNK_SIZE) as $chunk) {
            $deleted += DB::table($table)->whereIn($column, $chunk)->delete();
        }

        return $deleted;
    }

    /**
     * @param  list<int|string>  $values
     */
    private function nullifyIn(string $table, string $column, array $values): int
    {
        $updated = 0;

        foreach (array_chunk($values, self::CHUNK_SIZE) as $chunk) {
            $updated += DB::table($table)->whereIn($column, $chunk)->update([$column => null]);
        }

        return $updated;
    }

    /**
     * Die vorhandenen Werte einer Spalte zu einer Schluesselmenge.
     *
     * @param  list<int|string>  $values
     * @return list<int>
     */
    private function valuesIn(string $table, string $column, array $values, ?string $matchColumn = null): array
    {
        $found = [];

        foreach (array_chunk($values, self::CHUNK_SIZE) as $chunk) {
            $found = array_merge($found, DB::table($table)
                ->whereIn($matchColumn ?? $column, $chunk)
                ->pluck($column)
                ->map(fn ($value): int => (int) $value)
                ->all());
        }

        return array_values(array_unique($found));
    }

    private function record(string $relation, string $what, int $rows): void
    {
        if ($rows === 0) {
            return;
        }

        $this->touched[] = [$relation, $what, $rows];
    }

    private function report(bool $apply): void
    {
        $this->newLine();

        if ($this->touched === []) {
            $this->info('Keine verwaisten Zeilen gefunden.');
        } else {
            $this->table(
                ['Beziehung', 'Was geschehen ist', 'Zeilen'],
                array_map(fn (array $row): array => [$row[0], $row[1], (string) $row[2]], $this->touched),
            );

            $this->line(sprintf(
                '%d Zeilen in %d Beziehungen %s.',
                array_sum(array_column($this->touched, 2)),
                count($this->touched),
                $apply ? 'bereinigt' : 'waeren betroffen (Probe, nichts geschrieben)',
            ));
        }

        foreach ($this->problems as $problem) {
            $this->warn($problem);
        }

        if (! $apply) {
            $this->newLine();
            $this->comment('Probe. Mit --apply schreiben, danach "php artisan migrate" fuer die Fremdschluessel.');
        }
    }

    /**
     * Schreibt der Lauf? `null` heisst: der Mensch hat abgelehnt.
     */
    private function confirmedApply(): ?bool
    {
        if (! $this->option('apply')) {
            return false;
        }

        if ($this->option('force') || ! $this->input->isInteractive()) {
            return true;
        }

        return $this->confirm('Verwaiste Zeilen wirklich loeschen bzw. leeren? Das ist nicht umkehrbar.', false) ? true : null;
    }
}
