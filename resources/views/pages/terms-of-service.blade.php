{{--
    Allgemeine Geschaeftsbedingungen (Ticket #33).

    Loest den englischen Starterkit-Text ab, der ein allgemeines Abo-SaaS
    beschrieb. Der Inhalt bildet den tatsaechlichen Betrieb ab: Guthaben als
    Zahlmittel, Kauf einer Anfrage, Erreichbarkeitspruefung, Reklamation mit
    Gutschrift und Abrechnung nach Fristablauf.

    Der Anbieter kommt aus App\Services\CompanyProfile, also aus den
    Rechnungseinstellungen im Admin -- dieselbe Quelle wie Impressum,
    Datenschutzerklaerung und Rechnung. Fehlen die Angaben dort, bleibt der
    Block leer statt erfunden.

    Fristen, Versuchszahlen und Preise stehen nicht als Zahl im Text, sondern
    kommen aus config('funnel.*'); sonst laufen Bedingungen und Programm
    auseinander.

    Die Formulierungen sind fachlich, aber nicht anwaltlich geprueft.
--}}
<x-layouts.portal>

    <x-slot name="title">
        {{ __('Allgemeine Geschäftsbedingungen') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Bedingungen für den Kauf von Anfragen über widileads.') }}
    </x-slot>

    <section class="section">
        <div class="container-portal max-w-3xl">
            <h1 class="text-2xl font-semibold text-zinc-900 mb-6">Allgemeine Geschäftsbedingungen</h1>

            <div class="card p-6 space-y-8 text-zinc-600 leading-relaxed">

                <div>
                    <h2 class="font-semibold text-zinc-900 mb-2">1. Anbieter und Geltungsbereich</h2>
                    @if ($company->name())
                        <p>{{ $company->name() }}</p>
                    @endif
                    @if ($company->address())
                        <p class="whitespace-pre-line">{{ $company->address() }}</p>
                    @endif
                    @if ($company->email())
                        <p>E-Mail: <a class="hover:text-zinc-900" href="mailto:{{ $company->email() }}">{{ $company->email() }}</a></p>
                    @endif
                    @if ($company->isComplete())
                        <p class="mt-2">Weitere Angaben zum Anbieter stehen im <a class="hover:text-zinc-900 underline" href="{{ route('imprint') }}">Impressum</a>.</p>
                    @endif
                    <p class="mt-3">Diese Bedingungen gelten für alle Verträge über die Nutzung des Portals und den Kauf von Anfragen. Abweichende Bedingungen des Käufers werden nicht Vertragsinhalt, auch wenn wir ihnen nicht ausdrücklich widersprechen.</p>
                    <p class="mt-3">Das Angebot richtet sich ausschließlich an Unternehmer im Sinne von Paragraf 14 des Bürgerlichen Gesetzbuchs. Verbraucher können kein Konto führen und keine Anfragen kaufen. Ein Widerrufsrecht besteht daher nicht.</p>
                </div>

                <div>
                    <h2 class="font-semibold text-zinc-900 mb-2">2. Gegenstand des Vertrags</h2>
                    <p>Interessenten beantworten auf unseren Portalseiten einen Fragebogen, um ein Angebot für eine Dienstleistung zu erhalten. Aus einer solchen Anfrage entsteht ein Datensatz, den wir Fachbetrieben im Marktplatz zum Kauf anbieten. Wir vermitteln den Kontakt; die angefragte Leistung erbringt der Käufer selbst und in eigenem Namen.</p>
                    <p class="mt-3">Wir schulden keinen Vertragsabschluss zwischen Käufer und Interessent und keinen Umsatz. Gegenstand des Kaufs ist allein der Kontakt samt den Angaben, die der Interessent gemacht hat.</p>
                </div>

                <div>
                    <h2 class="font-semibold text-zinc-900 mb-2">3. Konto</h2>
                    <p>Für den Kauf ist ein Konto erforderlich. Die bei der Anmeldung gemachten Angaben müssen zutreffend sein und bei Änderungen berichtigt werden. Zugangsdaten sind geheim zu halten; für Vorgänge, die über das Konto ausgelöst werden, haftet der Käufer.</p>
                    <p class="mt-3">Wir dürfen die Eröffnung eines Kontos ohne Angabe von Gründen ablehnen.</p>
                </div>

                <div>
                    <h2 class="font-semibold text-zinc-900 mb-2">4. Guthaben und Preise</h2>
                    <p>Anfragen werden nicht einzeln in Euro bezahlt, sondern mit Guthaben. Guthaben wird im Voraus in Paketen erworben. Ein Guthaben kostet {{ number_format((float) config('funnel.marketplace.credit.unit_price'), 2, ',', '.') }} Euro zuzüglich Umsatzsteuer.</p>
                    <p class="mt-3">Der Kauf einer Anfrage kostet ein Guthaben. Reicht das Guthaben nicht, kommt kein Kauf zustande.</p>
                    <p class="mt-3">Die Zahlung der Pakete läuft über unsere Zahlungsdienstleister. Guthaben verfällt nicht. Eine Auszahlung nicht verbrauchten Guthabens in Geld ist nicht vorgesehen; bei Beendigung des Vertragsverhältnisses aus einem Grund, den wir zu vertreten haben, erstatten wir den auf nicht verbrauchtes Guthaben entfallenden Betrag.</p>
                    <p class="mt-3">Über jede Aufladung erhält der Käufer eine Rechnung in seinem Konto.</p>
                </div>

                <div>
                    <h2 class="font-semibold text-zinc-900 mb-2">5. Kauf einer Anfrage</h2>
                    <p>Der Käufer wählt eine Anfrage im Marktplatz aus oder lässt sie über ein Kaufprofil automatisch erwerben. Mit dem Kauf belasten wir ein Guthaben und geben die Kontaktdaten des Interessenten frei. Vor dem Kauf sieht der Käufer die fachlichen Angaben und einen unkenntlich gemachten Auszug der Kontaktdaten.</p>
                    <p class="mt-3">Eine für den Kauf vorgemerkte Anfrage bleibt {{ (int) config('funnel.lead.reservation_ttl') }} Minuten reserviert; danach ist sie wieder für andere Käufer verfügbar.</p>
                    <p class="mt-3">Eine Anfrage kann an mehr als einen Fachbetrieb verkauft werden, sofern nicht für das jeweilige Portal ausdrücklich Exklusivität ausgewiesen ist.</p>
                </div>

                <div>
                    <h2 class="font-semibold text-zinc-900 mb-2">6. Kontaktpflicht und Erreichbarkeitsprüfung</h2>
                    <p>Der Käufer ist verpflichtet, den Interessenten zeitnah zu kontaktieren. Die Anrufe löst er aus dem Portal heraus aus; die Verbindung stellt unser Telefonie-Dienstleister her und wird protokolliert. Gesprächsinhalte werden nicht aufgezeichnet.</p>
                    <p class="mt-3">Als ausgeschöpfte Kontaktversuche gelten {{ (int) config('funnel.call.max_failed_attempts') }} erfolglose Anrufe, die mindestens {{ (int) config('funnel.call.min_gap_hours') }} Stunden auseinanderliegen und sich auf mindestens {{ (int) config('funnel.call.min_days') }} verschiedene Kalendertage verteilen. Anrufe außerhalb des Portals können wir nicht berücksichtigen.</p>
                    <p class="mt-3">Der Käufer hat {{ (int) config('funnel.call.deadline_days') }} Tage ab Bereitstellung der Anfrage Zeit, seine Kontaktversuche abzuschließen.</p>
                </div>

                <div>
                    <h2 class="font-semibold text-zinc-900 mb-2">7. Reklamation und Gutschrift</h2>
                    <p class="mb-3">Innerhalb der Frist von {{ (int) config('funnel.call.deadline_days') }} Tagen nach dem Kauf kann der Käufer eine Anfrage in seinem Konto mit Begründung reklamieren. Anerkannt werden zwei Gründe:</p>
                    <ul class="list-disc pl-5 space-y-1">
                        <li><span class="font-medium text-zinc-900">Unerreichbar</span> – der Interessent war trotz ausgeschöpfter Kontaktversuche nach Abschnitt 6 nicht zu erreichen.</li>
                        <li><span class="font-medium text-zinc-900">Ungültig</span> – die Angaben sind unbrauchbar, etwa eine nicht existierende Rufnummer, ein offensichtlicher Scherzeintrag oder ein Anliegen, das nicht zum Portal gehört.</li>
                    </ul>
                    <p class="mt-3">Über eine Reklamation entscheiden wir nach Prüfung; die Anrufprotokolle sind dabei die Grundlage. Erkennen wir die Reklamation an, schreiben wir das beim Kauf belastete Guthaben zurück. Lehnen wir sie ab, teilen wir den Grund mit, und die Anfrage bleibt abgerechnet.</p>
                    <p class="mt-3">Ein unzureichendes Ergebnis ist kein Reklamationsgrund: Dass ein erreichter Interessent kein Angebot annimmt, seine Meinung ändert oder nicht zum Käufer passt, berührt den Kaufpreis nicht.</p>
                    <p class="mt-3">Reklamiert der Käufer nicht innerhalb der Frist, gilt die Anfrage als erreicht und endgültig abgerechnet. Eine verspätete Reklamation ist ausgeschlossen.</p>
                    <p class="mt-3">Reklamationen in erheblichem Umfang oder ohne erkennbare Grundlage können wir zum Anlass nehmen, das Konto zu prüfen und den automatischen Kauf auszusetzen.</p>
                </div>

                <div>
                    <h2 class="font-semibold text-zinc-900 mb-2">8. Pflichten des Käufers im Umgang mit den Daten</h2>
                    <p>Für die Verarbeitung der gekauften Kontaktdaten ist der Käufer eigenständig verantwortlich. Er darf sie ausschließlich dazu verwenden, das angefragte Anliegen zu bearbeiten, und hat die Betroffenen auf Verlangen zu informieren, zu berichtigen oder zu löschen.</p>
                    <p class="mt-3">Untersagt sind insbesondere die Weitergabe oder der Weiterverkauf der Daten an Dritte, die Nutzung für Werbung, die nichts mit der Anfrage zu tun hat, sowie das Auslesen von Daten aus dem Marktplatz mit automatisierten Mitteln. Widerruft ein Interessent seine Einwilligung, teilen wir das mit; der Käufer hat die Kontaktaufnahme dann zu unterlassen.</p>
                </div>

                <div>
                    <h2 class="font-semibold text-zinc-900 mb-2">9. Verfügbarkeit</h2>
                    <p>Wir betreiben das Portal mit der üblichen Sorgfalt, schulden aber keine ununterbrochene Verfügbarkeit. Wartungsarbeiten, Störungen bei Vorleistern und Ereignisse außerhalb unseres Einflussbereichs können den Betrieb zeitweise einschränken. Wir schulden weder eine bestimmte Anzahl noch eine bestimmte Art von Anfragen.</p>
                </div>

                <div>
                    <h2 class="font-semibold text-zinc-900 mb-2">10. Haftung</h2>
                    <p>Wir haften unbeschränkt bei Vorsatz und grober Fahrlässigkeit sowie für Schäden aus der Verletzung des Lebens, des Körpers oder der Gesundheit. Bei einfacher Fahrlässigkeit haften wir nur für die Verletzung einer wesentlichen Vertragspflicht und begrenzt auf den vorhersehbaren, vertragstypischen Schaden. Die Haftung für entgangenen Gewinn ist ausgeschlossen.</p>
                    <p class="mt-3">Für die Richtigkeit der Angaben, die ein Interessent selbst gemacht hat, können wir nicht einstehen. Offensichtlich unbrauchbare Anfragen sind über Abschnitt 7 abgedeckt.</p>
                </div>

                <div>
                    <h2 class="font-semibold text-zinc-900 mb-2">11. Laufzeit und Kündigung</h2>
                    <p>Das Vertragsverhältnis läuft auf unbestimmte Zeit und kann von beiden Seiten jederzeit in Textform gekündigt werden. Bei einem erheblichen Verstoß gegen diese Bedingungen, insbesondere gegen Abschnitt 8, können wir das Konto sperren und aus wichtigem Grund kündigen. Laufende Reklamationen werden auch nach einer Kündigung entschieden.</p>
                </div>

                <div>
                    <h2 class="font-semibold text-zinc-900 mb-2">12. Änderungen dieser Bedingungen</h2>
                    <p>Wir dürfen diese Bedingungen ändern, wenn dafür ein sachlicher Grund besteht, etwa geänderte Abläufe im Portal oder eine geänderte Rechtslage. Die Änderung kündigen wir mit angemessener Frist in Textform an. Widerspricht der Käufer nicht vor Inkrafttreten, gilt die neue Fassung als angenommen; widerspricht er, können beide Seiten zum Zeitpunkt des Inkrafttretens kündigen.</p>
                </div>

                <div>
                    <h2 class="font-semibold text-zinc-900 mb-2">13. Schlussbestimmungen</h2>
                    <p>Es gilt deutsches Recht. Ausschließlicher Gerichtsstand ist unser Sitz, soweit der Käufer Kaufmann, juristische Person des öffentlichen Rechts oder öffentlich-rechtliches Sondervermögen ist. Ist eine Bestimmung unwirksam, bleibt der übrige Vertrag wirksam.</p>
                    <p class="mt-3">Wie wir personenbezogene Daten verarbeiten, steht in der <a class="hover:text-zinc-900 underline" href="{{ route('privacy-policy') }}">Datenschutzerklärung</a>.</p>
                </div>

            </div>
        </div>
    </section>

</x-layouts.portal>
