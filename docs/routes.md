# Routen und ihre Zugangskontrolle

Diese Datei beschreibt, welche Sperren vor den angemeldeten Bereichen stehen und
in welcher Reihenfolge sie laufen. Die Reihenfolge ist Fachlogik und keine
Formsache: Eine Sperre, die nach dem Mandantenkontext läuft, hat ihn schon
gesetzt — der gesperrte Nutzer hätte also bekommen, was er nicht bekommen soll.

Festgeschrieben wird die Reihenfolge an zwei Stellen, die zusammen gelesen
werden müssen:

- **`bootstrap/app.php`** — die Prioritätsliste (`prependToPriorityList`). Sie
  gilt, egal wie jemand das Middleware-Array einer Route sortiert.
- **`routes/web.php`** — die Liste je Routengruppe. Sie nennt die Middlewares in
  derselben Folge, damit die Absicht beim Lesen der Routen sichtbar ist.

## Onboarding-Gate

Die harte Sperre bis zur bestätigten Rufnummer (LP-ONBOARD-003).

### Regel

Ein angemeldeter Nutzer ohne bestätigte Rufnummer sieht **nichts** vom System:
keinen Marktplatz, kein Dashboard, keine Navigation, keine Lead-Daten. Er landet
ausschließlich auf dem Verifizierungs-Screen `onboarding.phone`
(`/onboarding/telefon`).

Das ist eine Sicherheits- und Geschäftsregel, kein UX-Komfort: Ohne bestätigte
Nummer kann ein Käufer einen gekauften Lead nicht anrufen, und der Anruf ist der
einzige Weg, auf dem ein Lead abgerechnet oder freigegeben wird. Ein Kauf davor
wäre Geld ohne Gegenwert.

**Eine Regel für alle.** Verkäufer-Workspaces sind nicht ausgenommen. Die
Wizard-Schritte (LP-ONBOARD-006 ff.) gelten dagegen nur für Käufer-Workspaces
und stehen nicht in der Routen-Middleware.

### Wo die Wahrheit liegt

`users.phone_number_verified_at`, gelesen über
`OnboardingService::isPhoneVerified()`. Also **am Benutzer und nicht am
Workspace**: Ein zweiter Kollege im selben Workspace bestätigt seine eigene
Nummer. In keiner Sitzung liegt ein Stand — wer im Admin auf `null` gesetzt wird
(oder über `UserService::anonymize`), ist beim nächsten Request wieder gesperrt,
ohne Abmelden und ohne Cache.

Der Schritt `phone_verified` in `tenant_onboarding_steps` ist nur die Spiegelung
fürs Abhaken in der Checkliste und **nie** die Grundlage der Sperre.

### Reihenfolge

```
auth → verified (E-Mail) → portal.phone-verified → portal.tenant
     → SubstituteBindings
```

- `portal.phone-verified` = `App\Http\Middleware\EnsurePhoneVerified`
- Sie steht **vor** `portal.tenant` (`ResolvePortalTenant`) und damit auch vor
  der Route-Modellbindung, weil `ResolvePortalTenant` selbst vor
  `SubstituteBindings` gezogen ist.
- `Illuminate\Auth\Middleware\Authenticate` steht in Laravels
  Standard-Prioritätsliste und läuft vor `SubstituteBindings` —
  `$request->user()` ist in der Sperre also nie `null`.

### Wo die Sperre hängt

| Ort | Datei |
| --- | --- |
| Portalgruppe `/portal/{tenant:uuid}/…` (Käufer- **und** Verkäuferseiten) | `routes/web.php` |
| `/portal` (Weiterleitung in den Workspace) | `routes/web.php` |
| Geldwege des Käufers: Aufladung, Zahlungsmittel, Abrechnungsbeleg, Click-to-Call | `routes/web.php` |
| Käuferregistrierung `/kaeufer/registrierung` | `routes/web.php` |
| Dashboard-Panel (Filament) — über `authMiddleware`, nicht `middleware` | `app/Providers/Filament/DashboardPanelProvider.php` |
| Livewire-Folgeanfragen `/livewire/update` | `app/Providers/AppServiceProvider.php` |

Das Admin-Panel ist **nicht** gesperrt: Es ist der Arbeitsplatz des Betreibers
und nicht Teil des Nutzerbereichs.

Die Geldwege gehören dazu, weil sie die Formularziele der Portalseiten sind.
Ohne sie wäre die Sperre eine reine Sichtsperre: Der gesperrte Nutzer sieht die
Seite nicht, könnte aber weiter aufladen, ein Zahlungsmittel hinterlegen oder
einen Lead anrufen lassen.

### Whitelist

Freigeschaltet wird über **Routennamen** (`Str::is`-Muster, Konstante
`EnsurePhoneVerified::ALLOWED_ROUTE_NAMES`). Jeder Eintrag ist ein Loch in der
Sperre und trägt deshalb seinen Grund im Code:

| Muster | Grund |
| --- | --- |
| `onboarding.phone`, `onboarding.phone.*` | Der Screen selbst — sonst leitete er auf sich selbst um. |
| `logout` | Eine Sperre, aus der man nicht herauskommt, ist eine Falle. |
| `account.delete` | Konto löschen bleibt möglich (DSGVO). Die Route existiert noch nicht; der Eintrag steht vorab, damit sie nicht versehentlich hinter der Sperre landet. |
| `imprint`, `privacy-policy`, `terms-of-service` | Pflichtangaben und Rechtstexte, sie stehen im Fuß jedes Rahmens. |
| `verification.*` | Die E-Mail-Bestätigung läuft vor dieser Sperre ab; ihre Seiten dürfen nicht zwischen zwei Gates hängen. |

Eine Route **ohne** Namen wird gesperrt. Im Zweifel sperren.

### Rückkehr nach der Bestätigung

Die Sperre merkt die angefragte Adresse in `url.intended` — derselbe Weg, den
Laravels `Authenticate` nimmt, und nur bei `GET` (eine gemerkte POST-Adresse
endete nach der Bestätigung in 405). `onboarding.phone` führt sie mit
`redirect()->intended(route('portal.home'))` aus, sobald die Nummer bestätigt
ist. Damit greift die Rückkehr auch nach E-Mail-Verifizierung,
Magic-Link-Login, Passwort-Reset und Workspace-Einladung: Alle vier landen
zuerst auf der gesperrten Zieladresse, die damit gemerkt ist.

### Livewire und JSON

Livewire-Folgeanfragen und alles unter `/api/*` bekommen **keinen**
HTML-Redirect, sondern:

```
HTTP 403
{"reason": "phone_unverified", "redirect": "https://…/onboarding/telefon"}
```

Ein 302 auf eine `fetch()`-Anfrage würde stillschweigend verfolgt: Der Browser
bekäme das HTML des Verifizierungs-Screens als Livewire-Antwort und würfe es
weg — der Nutzer sitzt weiter auf der gesperrten Seite, nur ohne Reaktion auf
seinen Klick. `resources/js/modules/auth-guard.js` (geladen über
`resources/js/portal.js`, also in jedem Portalrahmen) fängt die Antwort an
Livewires `request`-Hook und an `window.fetch` ab und leitet den Browser um.

### Der Screen

`onboarding.phone` rendert im Fokus-Rahmen
(`components.layouts.portal-focus`): keine Sidebar, keine Guthaben-Anzeige, kein
Workspace-Wechsler. Es werden **keine** Workspace-Daten geladen — der
View-Composer für den Onboarding-Stand (`OnboardingComposer`) hängt an
`components.layouts.portal-app` und läuft hier deshalb gar nicht.

Die Route trägt bewusst **keinen** Workspace im Pfad: Die Sperre greift, bevor
ein Workspace gewählt ist, und die Bestätigung gilt für alle Workspaces des
Nutzers.

### Schalter

`config('funnel.onboarding.phone_gate')`, Umgebungsvariable
`FUNNEL_ONBOARDING_PHONE_GATE`, Vorgabe `true`.

Der Schalter ist kein Schmuck: Bestätigungsanrufe sind auf einem
Twilio-Trial-Konto gesperrt (HTTP 400, „Placing verification calls is not
supported on trial accounts"). Auf einem solchen Konto käme kein Nutzer je an
der Sperre vorbei. (Der gleichnamige Schalter
`funnel.call.caller_id.required_for_portal` sperrt seit Ticket #49 nichts mehr
und steuert nur noch den Hinweis auf die Rufnummernanzeige.)

## Wizard-Gate

Der Einrichtungs-Wizard als Pflichtweg (LP-ONBOARD-007).

### Regel

Ein **Käufer-Workspace** ohne `onboarding_completed_at` und ohne
`onboarding_skipped_at` wird von jeder Portaladresse auf den offenen
Wizard-Schritt umgeleitet: `portal.onboarding`
(`/portal/{tenant:uuid}/onboarding/{industries|region|payment}`).

Das ist Führung und keine Zugriffskontrolle: Ohne Branchen, Region und
Zahlungsweise zeigt der Marktplatz einem Käufer eine leere Liste, und eine leere
Liste liest sich wie ein leeres Angebot. Deshalb Umleitung statt 403 — das Ziel
ist der nächste Schritt und keine Sackgasse.

### Kette

```
auth → verified (E-Mail) → portal.phone-verified → portal.tenant
     → portal.wizard → SubstituteBindings
```

- `portal.wizard` = `App\Http\Middleware\EnsureOnboardingWizard`
- Sie steht **nach** `portal.tenant` (ohne Mandant ist nichts zu entscheiden).
  Ein Eintrag in der Prioritätsliste ist nicht nötig: `portal.tenant` steht dort
  und läuft ohnehin vorher.

### Ausnahmen

`portal.onboarding*` (sonst leitete der Wizard auf sich selbst um), `logout`,
`account.delete` (DSGVO) und `portal.caller-id`. Die letzte bleibt frei, damit
ein Käufer die Seite aus dem Dashboard-Hinweis heraus ansteuern kann, auch
solange der Wizard offen ist — die Rufnummernanzeige ist Voraussetzung für den
Anruf, und wer sie regeln will, soll das nicht erst nach dem Wizard dürfen.

### Nicht persistent bei Livewire

Anders als das Onboarding-Gate ist `EnsureOnboardingWizard` **keine**
persistente Livewire-Middleware. Folgeanfragen an `/livewire/update` laufen
ungeprüft durch — richtig so: Die Sperre schützt keine Daten, sie weist den Weg.

### Schalter

`FUNNEL_ONBOARDING_WIZARD_GATE`, Vorgabe `true`. Für die manuelle Abnahme und
für Umgebungen, in denen der Wizard im Weg steht.

## Verwandte Sperren

- **Die Rufnummernanzeige bei Twilio** (`PortalCallerIdLock`) ist **keine
  Sperre mehr** (Ticket #49). `portal.verified-number`
  (`RequireVerifiedCallerId`) ist entfallen: Sie unterschied sich vom
  Onboarding-Gate in genau einem Fall — einer `caller_ids`-Zeile in `failed`
  oder `expired` — und verlangte dort einen zweiten Nachweis für eine Nummer,
  die Twilio Verify kurz vorher bestätigt hatte. Geprüft wird die Nummer heute
  nur noch dort, wo sie gebraucht wird: im `CallService`
  (`call.attempt.errors.caller_id_missing`). `PortalCallerIdLock` beantwortet
  nur noch die Frage „fehlt eine nutzbare Nummer?" für den Hinweis auf
  `portal.caller-id`. Seit Ticket #97 gibt es den zweiten Nachweis gar nicht
  mehr: Twilio Verify ist abgebaut, bestätigt wird genau einmal über den
  Bestätigungsanruf, und derselbe Vorgang setzt auch
  `users.phone_number_verified_at` (`CallerIdService::completeValidation()`).
- **`marketplace.access`** — ein Gate und keine Routen-Middleware: Es gehört in
  `booted()` der jeweiligen Livewire-Komponente, damit auch jede Folgeanfrage
  durch die Prüfung geht.
- **`tenant.type`** (`EnsureTenantType`) — trennt Käufer- und Betreiberbereiche.
