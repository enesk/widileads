# Embed-Schnittstelle v1

Fremde Webseiten binden eine Funnel-Strecke über ein Skript ein:

```html
<script src="https://plattform.example/embed/v1.js"
        data-funnel="01J8ZTOKEN…"
        async></script>
```

**Diese Schnittstelle ist ein Versprechen.** Die URL steht im Quelltext fremder
Webseiten, auf die wir keinen Zugriff haben — wir bekommen sie dort nie wieder heraus.
Deshalb gilt: **v1 wird nur erweitert, nie verändert.** Neue `data-`Attribute und neue
Nachrichtentypen sind erlaubt, solange bestehende Einbindungen unverändert
weiterfunktionieren. Alles andere bekommt eine `v2.js` unter eigener URL; `v1.js` bleibt
dauerhaft erreichbar.

## Attribute am Skript-Tag

| Attribut | Pflicht | Vorgabe | Bedeutung |
|---|:-:|---|---|
| `data-funnel` | ja | — | Öffentlicher Token des Funnels (ULID). Ohne ihn passiert nichts. |
| `data-mode` | nein | `inline` | `inline` bettet direkt ein, `overlay` zeigt einen Button und öffnet die Strecke in einer Ebene darüber. |
| `data-height` | nein | `600` | Anfangshöhe in Pixeln, bis die erste `resize`-Nachricht kommt. |
| `data-button-label` | nein | `Jetzt starten` | Beschriftung des Overlay-Buttons. |
| `data-close-label` | nein | `Schliessen` | `aria-label` des Schließen-Knopfes im Overlay. |
| `data-title` | nein | `Funnel` | `title` des iFrames (für Screenreader). |
| `data-base` | nein | aus der Skript-URL abgeleitet | Basis-URL der Plattform. Nur nötig, wenn Skript und Anwendung getrennt ausgeliefert werden. |

Das Skript erzeugt daraus ein iFrame auf:

```
{base}/f/{token}?embed=1&origin={origin der einbettenden Seite}
```

`embed=1` schaltet das eingebettete Layout (kein Seitenhintergrund, schlankere
Abstände), `origin` ist das Ziel der Nachrichten aus dem iFrame.

## Nachrichten (postMessage)

Jede Nachricht **aus dem iFrame** trägt:

```json
{ "source": "widileads-funnel", "version": 1, "token": "01J8Z…", "type": "…" }
```

| `type` | Zusatzfeld | Wann |
|---|---|---|
| `ready` | — | Die Strecke ist geladen. |
| `resize` | `height` (Zahl) | Der Inhalt hat seine Höhe geändert. |
| `submitted` | — | Die Anfrage wurde abgeschickt. |
| `close` | — | Die Strecke bittet darum, ein Overlay zu schließen. |

**Zwei Regeln, an die sich beide Seiten halten:**

1. Das iFrame sendet **gezielt an den `origin`** der einbettenden Seite, nie an `*`.
   Sonst könnte jede Seite mitlesen, die das iFrame in ein eigenes einbettet.
2. Das Skript verarbeitet eine Nachricht nur, wenn `event.origin` der Plattform
   entspricht **und** `event.source` das eigene iFrame ist **und** `source`, `version`
   und `token` stimmen. Ohne diese Prüfung könnte jede beliebige Seite die Höhe des
   iFrames fernsteuern.

## Ereignisse für die einbettende Seite

Jede empfangene Nachricht wird als DOM-Ereignis auf `document` weitergereicht — so kann
die fremde Seite ihr eigenes Tracking anhängen, ohne unsere Interna zu kennen:

```js
document.addEventListener('funnel:submitted', function (event) {
    // event.detail enthält die Nachricht
});
```

Ereignisnamen: `funnel:ready`, `funnel:resize`, `funnel:submitted`, `funnel:close`.

## Herkunft und Allowlist

Der `origin` der einbettenden Seite wird an der Sitzung gespeichert
(`public_sessions.embed_origin`, FB-022). Ob ein Funnel auf einer bestimmten Seite
eingebettet werden **darf**, entscheidet `App\Funnel\Runtime\EmbedOriginPolicy` — heute
erlaubt sie jede Herkunft, ab FB-025 prüft sie gegen `funnel_origins`. Die Entscheidung
liegt bewusst an genau einer Stelle, damit die Runtime dafür nicht angefasst werden muss.

## Testseite

`GET /dev/embed-test` (nur lokal) simuliert eine fremde Webseite: Sie kennt nur die
Skript-URL und den Token, bindet Inline- und Overlay-Modus ein und protokolliert alle
empfangenen Nachrichten.
