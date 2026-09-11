# Teil 2 — Zieldatenmodell (verbindlich für alle Tickets)

Vor dem Anlegen einer Tabelle ist dieses Modell zu lesen. Es gilt unverändert
gegenüber dem Ursprungsdokument.

```
tenants (SaasyKit) ─┬─ tenant_user (SaasyKit, + role)
                    ├─ api_tokens (Sanctum)
                    ├─ funnels ─┬─ funnel_steps ── funnel_questions ── funnel_options
                    │           ├─ funnel_conditions        (Verzweigungslogik)
                    │           ├─ funnel_results           (Ergebnis-Screens, Scoring-Bereiche)
                    │           ├─ funnel_themes            (Farben, Logo, Schrift, Texte)
                    │           ├─ funnel_origins           (CORS-Allowlist)
                    │           ├─ funnel_webhooks
                    │           └─ funnel_versions          (publizierte Snapshots als JSON)
                    ├─ sessions_public ─ session_events     (Aufruf, Schritt, Abbruch, Absenden)
                    ├─ leads ─┬─ lead_answers               (Rohantworten, Feldschlüssel → Wert)
                    │         ├─ lead_state_log             (jeder Übergang, unveränderlich)
                    │         ├─ lead_notes
                    │         ├─ lead_purchases             (Kauf durch buyer-Tenant)
                    │         └─ call_attempts              (Phase 2, Beweisprotokoll)
                    ├─ buyer_profiles                       (Kaufkriterien je buyer-Tenant)
                    ├─ wallets ─ wallet_transactions        (Geldbuchungen, append-only)
                    └─ audit_logs
```

**Lead-Lebenszyklus (`LeadState` Enum):**

| Wert | Bedeutung | Endzustand |
|---|---|---|
| `neu` | vollständige Anfrage, noch nicht zum Kauf freigegeben (Spam-/Dublettenprüfung läuft) | |
| `verfuegbar` | im Marktplatz sichtbar, kaufbar | |
| `reserviert` | von einem Käufer in den Warenkorb gelegt (Timeout 10 min) | |
| `verkauft` | gekauft, Kontaktdaten für Käufer freigegeben, Preis festgeschrieben | |
| `erreicht` | Kontakt nachgewiesen (Phase 2) → Abrechnung | ✓ |
| `unerreichbar` | Kontaktversuche nachweislich ausgeschöpft → Gutschrift | ✓ |
| `ungueltig` | Spam / Fehleingabe / Dublette, keine Abrechnung | ✓ |
| `abgelaufen` | nie verkauft, Aufbewahrungsfrist erreicht | ✓ |

Erlaubte Übergänge stehen in `LeadTransitions::TABLE` (FB-030). Alles andere wirft `IllegalLeadTransition`.
