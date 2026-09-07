<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\BuyerProfile;
use App\Models\BuyerRegistration;
use App\Models\CreditLedgerEntry;
use App\Models\Funnel;
use App\Models\FunnelCondition;
use App\Models\FunnelOption;
use App\Models\FunnelOrigin;
use App\Models\FunnelQuestion;
use App\Models\FunnelResult;
use App\Models\FunnelStep;
use App\Models\FunnelTheme;
use App\Models\FunnelVersion;
use App\Models\Lead;
use App\Models\LeadPurchase;
use App\Models\LeadStateLog;
use App\Models\LeadWatchlistEntry;
use App\Models\PublicSession;
use App\Models\SessionEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Support\ServiceProvider;

/**
 * Faengt N+1-Abfragen auf den Modellen des Funnel Builders (FB-041).
 *
 * `Model::preventLazyLoading()` laesst sich nur global schalten. Global ist es
 * hier aber nicht brauchbar: Die mitgelieferten SaaSykit-Modelle laden an vielen
 * Stellen nach -- Spaties `HasRoles` etwa liest die Rollen bei jeder
 * Berechtigungspruefung --, und der Bestand wird laut Ticket nicht umgebaut.
 * Waere der Waechter global, stuenden ~36 rote Tests im Weg, die mit unserem
 * Code nichts zu tun haben, und niemand koennte den Waechter ernst nehmen.
 *
 * Deshalb bleibt `preventLazyLoading` an, der Verstoss wird aber nur fuer die
 * Modelle gemeldet, die uns gehoeren. Fuer alle anderen laedt Eloquent wie
 * bisher nach.
 *
 * Nur in der Testumgebung: Im Betrieb ist eine zusaetzliche Abfrage
 * unschoen, eine Ausnahme vor dem Benutzer waere schlimmer.
 *
 * Gut zu wissen beim Schreiben eines Tests: Laravel markiert ein Modell nur
 * dann als geschuetzt, wenn die Abfrage mehr als eine Zeile geliefert hat
 * (Builder::hydrate). Ein einzeln geladenes Modell schlaegt also nie an -- und
 * das ist richtig, denn ein einzelner Datensatz hat kein N+1-Problem.
 */
class LazyLoadingGuardServiceProvider extends ServiceProvider
{
    /**
     * Die Modelle des Funnel Builders.
     *
     * Neue Funnel-Modelle gehoeren hier hinein -- sonst laeuft der Waechter an
     * ihnen vorbei. Mitgelieferte SaaSykit-Modelle stehen bewusst nicht in der
     * Liste.
     *
     * Das Antwortmodell des Leads fehlt aus zwei Gruenden. Fachlich bringt es
     * wenig: Gemeldet wird immer das Modell, das die Beziehung besitzt, und der
     * teure Fall ist das Nachladen der Antworten am Lead -- der ist ueber
     * Lead::class abgedeckt. Dazu kommt, dass der Architektur-Test aus FB-042
     * schon den blossen Klassennamen als Zugriff auf die Antworten wertet; er
     * kann an dieser Stelle nicht unterscheiden, ob etwas gelesen oder nur
     * aufgezaehlt wird. Der Fehlalarm ist gemeldet.
     *
     * @var list<class-string<Model>>
     */
    public const GUARDED_MODELS = [
        AuditLog::class,
        BuyerProfile::class,
        BuyerRegistration::class,
        CreditLedgerEntry::class,
        Funnel::class,
        FunnelCondition::class,
        FunnelOption::class,
        FunnelOrigin::class,
        FunnelQuestion::class,
        FunnelResult::class,
        FunnelStep::class,
        FunnelTheme::class,
        FunnelVersion::class,
        Lead::class,
        LeadPurchase::class,
        LeadStateLog::class,
        LeadWatchlistEntry::class,
        PublicSession::class,
        SessionEvent::class,
    ];

    public function boot(): void
    {
        if (! $this->app->environment('testing')) {
            return;
        }

        Model::preventLazyLoading();

        Model::handleLazyLoadingViolationUsing(
            static function (Model $model, string $relation): void {
                if (! in_array($model::class, self::GUARDED_MODELS, true)) {
                    return;
                }

                throw new LazyLoadingViolationException($model, $relation);
            }
        );
    }
}
