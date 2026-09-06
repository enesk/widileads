<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://saasykit.com/images/logo-dark.png" width="400" alt="Laravel Logo"></a></p>

## About SaaSykit
**SaaSykit** is a SaaS starter kit (boilerplate) that comes packed with all components required to run a modern SaaS software.

**SaaSykit** is built using the beautiful Laravel framework (using [TALL](https://tallstack.dev/)) and offers an intuitive Filament admin panel that houses all the pre-built components like product, plans, discounts, payment providers, email providers, transactions, blog, user & role management, and much more.

**SaaSykit** is developer-friendly, uses best coding practices, comes with an ever-growing automated tests that cover the critical components that it offers.

## Features in a nutshell

* Customize Styles: Customize the styles & colors, error page of your application to fit your brand.
* Product, Plans & Pricing: Create and manage your products, plans, and pricing from a beautiful and easy-to-use admin panel.
* Beautiful checkout process: Your customers can subscribe to your plans from a beautiful checkout process.
* Huge list of ready-to-use components: Plans & Pricing, hero section, features section, testimonials, FAQ, Call to action, tab slider, and much more.
* User authentication: Comes with user authentication out of the box, whether classic email/password or social login (Google, Facebook, Twitter, Github, LinkedIn, and more).
* Discounts: Create and manage your discounts and reward your customers.
* SaaS metric stats: View your MRR, Churn rates, ARPU, and other SaaS metrics.
* Multiple payment providers: Stripe, Paddle, and more coming soon.
* Multiple email providers: Mailgun, Postmark, Amazon SES, and more coming soon.
* Blog: Create and manage your blog posts.
* User & Role Management: Create and manage your users and roles, and assign permissions to your users.
* Fully translatable: Translate your application to any language you want.
* Sitemap & SEO: Sitemap and SEO optimization out of the box.
* Admin Panel: Manage your SaaS application from a beautiful admin panel powered by [Filament](https://filamentphp.com/).
* User Dashboard: Your customers can manage their subscriptions, change payment method, upgrade plan, cancel subscription, and more from a beautiful user dashboard powered by [Filament](https://filamentphp.com/).
* Automated Tests: Comes with automated tests for critical components of the application.
* One-line deployment: Provision your server and deploy your application easily with integrated [Deployer](https://deployer.org/) support.
* Developer-friendly: Built with developers in mind, uses best coding practices.
* And much more...

For more details, check the [documentation](https://saasykit.com/docs).

---

# Funnel Builder

Der Funnel Builder ist die auf SaaSykit Tenancy aufsetzende Plattform, mit der
dynamische Lead-Funnels (Quiz-Funnels) angelegt, ueber eine REST-API verwaltet,
eingebettet und die daraus entstehenden Leads an Kaeufer verkauft werden.

## Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed   # NICHT migrate:fresh - siehe "Datenschutz-Guard"
npm run dev
```

Die relevanten Env-Werte fuer den Funnel Builder stehen im Block
`Funnel Builder` in `.env.example`. Alle Schwellwerte der Plattform liegen in
`config/funnel.php`; jeder Key hat einen Env-Fallback und einen Kommentar.
Werte aus dieser Datei duerfen nirgends im Code hartkodiert werden.

## Feature-Flags

Die mitgelieferten SaaSykit-Module Blog, Roadmap, Announcements und Referral
sind standardmaessig **aus**. Bei deaktiviertem Flag werden weder die
oeffentlichen Routen noch die Navigationseintraege registriert - `/blog` und
`/roadmap` liefern dann 404.

| Env                                    | Wirkung                                    |
| -------------------------------------- | ------------------------------------------ |
| `FUNNEL_FEATURE_BLOG_ENABLED`          | `/blog` + Admin-Resources fuer Blog        |
| `FUNNEL_FEATURE_ROADMAP_ENABLED`       | `/roadmap` + Admin-Resource fuer Roadmap   |
| `FUNNEL_FEATURE_ANNOUNCEMENTS_ENABLED` | Announcement-Banner + Admin-Resource       |
| `FUNNEL_FEATURE_REFERRAL_ENABLED`      | Referral-Programm + Admin-/Dashboard-Views |

Die Module bleiben im Code erhalten und werden ausschliesslich ueber diese
Flags deaktiviert.

## Datenschutz-Guard fuer destruktive Befehle

`migrate:fresh`, `migrate:refresh`, `migrate:reset` und `db:wipe` sind in
**jeder** Umgebung gesperrt und brechen mit Exit-Code 1 ab - auch mit `--force`.
Verkaufte Leads sind nicht rekonstruierbar, deshalb greift der Schutz auch
lokal. Fuer Schema-Aenderungen `php artisan migrate` verwenden.

Einzige Ausnahme ist die Testsuite: `APP_ENV=testing` **und**
`FUNNEL_ALLOW_DESTRUCTIVE=1` (in `phpunit.xml` und `.env.testing` gesetzt).
`FUNNEL_ALLOW_DESTRUCTIVE` niemals ausserhalb der Testumgebung auf `1` setzen.

## Sprache

Die Anwendung laeuft auf Deutsch (`APP_LOCALE=de`). UI-Strings gehoeren nach
`lang/de/*.php` - keine hartkodierten Strings im Code. Das frueher parallel
existierende `resources/lang/` wurde entfernt; `lang/` ist das einzige
Uebersetzungsverzeichnis.

## Queues, Horizon & Redis

Geprueft und einsatzbereit, mit folgenden Voraussetzungen:

- **Redis** wird von `compose.yml` als Service `redis` bereitgestellt. Als
  Client wird `phpredis` verwendet (`REDIS_CLIENT`, Default `phpredis`) - die
  PHP-Extension `redis` muss installiert sein, andernfalls `REDIS_CLIENT=predis`
  setzen und `predis/predis` ergaenzen.
- **`QUEUE_CONNECTION=redis`** setzen. `.env.example` liefert `sync` aus; damit
  laeuft Horizon zwar, verarbeitet aber keine Jobs.
- **Horizon starten** mit `php artisan horizon` (lokal) bzw. per Supervisor im
  Deployment. Dashboard: `/horizon`, Zugriff ueber das Gate `viewHorizon`
  (nur Admin-User, siehe `app/Providers/HorizonServiceProvider.php`).
- **Supervisor-Konfiguration** in `config/horizon.php` existiert fuer
  `production` und `local`.

Offene Punkte dazu (fehlender `horizon:snapshot`-Schedule, fehlende
`staging`-Umgebung) stehen in [docs/BACKLOG.md](docs/BACKLOG.md).

## Qualitaets-Check

```bash
composer check     # Pint (dry run) + PHPStan + PHPUnit
```

Einzeln:

```bash
vendor/bin/pint --format agent      # Formatierung korrigieren
vendor/bin/phpstan analyse          # Larastan, Level 6
php artisan test --compact          # PHPUnit
```

### Testdeterminismus

Die Suite laeuft in **zufaelliger Reihenfolge** (`executionOrder="random"`). Das Ergebnis
darf davon nicht abhaengen:

```bash
composer check:determinism   # drei Laeufe mit den festen Seeds 1, 2 und 3
```

Alle drei Laeufe muessen dasselbe Ergebnis liefern. Schlaegt einer fehl, laesst er sich
mit dem ausgegebenen Seed exakt reproduzieren:

```bash
php artisan test --order-by=random --random-order-seed=<seed>
```

Zwei Regeln halten das offen:

- Ein neuer statischer Cache in `app/` gehoert in
  `Tests\TestCase::applicationStateFlushers()`, sonst leckt er von einem Testfall in den
  naechsten.
- Eine neue Testklasse erbt von `Tests\Feature\FeatureTest` und bekommt damit
  `RefreshDatabase`. Nur Faelle, die die Datenbank nachweislich nicht brauchen, duerfen
  direkt von `Tests\TestCase` erben.

`php artisan test --parallel` funktioniert ebenfalls und halbiert etwa die Laufzeit.

**Jede Arbeitskopie bekommt ihre eigene Test-Datenbank.** `RefreshDatabase` baut die
Datenbank zu Beginn eines Laufs per `migrate:fresh` neu auf — teilen sich zwei Checkouts
denselben Namen, reisst ein parallel laufender Testlauf dem anderen die Tabellen weg.
`Tests\TestCase` leitet den Namen deshalb aus dem Pfad der Arbeitskopie ab
(`funnel_test_<verzeichnis>_<hash>`) und legt die Datenbank beim ersten Lauf selbst an.

Wer die Datenbank selbst bestimmen will — etwa in CI —, setzt `DB_DATABASE`; ein
ausdruecklich gesetzter Wert hat immer Vorrang:

```bash
DB_DATABASE=meine_test_db composer check
```

Der Datenbank-Benutzer braucht dafuer das Recht, Datenbanken anzulegen. Fehlt es,
bricht der Lauf mit einer Meldung ab, die genau das sagt.

Larastan laeuft auf Level 6. Die Fehler des Bestandscodes sind in
`phpstan-baseline.neon` eingefroren, neuer Code wird voll geprueft. Die Baseline
darf nur schrumpfen - Details und Abbauplan in [docs/BACKLOG.md](docs/BACKLOG.md).

Aenderungen werden in [docs/CHANGELOG.md](docs/CHANGELOG.md) festgehalten.
