{{--
    Datenschutzerklaerung (Ticket #33).

    Loest den englischen Starterkit-Text ab, der ein allgemeines Abo-SaaS
    beschrieb. Der Inhalt bildet den tatsaechlichen Betrieb des Lead-Portals ab:
    Anfragen aus den Funnels, Weitergabe an Kaeufer nach dem Kauf,
    Anrufvermittlung ueber Twilio, Aufbewahrung und Anonymisierung.

    Der Verantwortliche kommt aus App\Services\CompanyProfile, also aus den
    Rechnungseinstellungen im Admin -- dieselbe Quelle wie Impressum und
    Rechnung. Fehlen die Angaben dort, bleibt der Block leer statt erfunden.

    Fristen stehen nicht als Zahl im Text, sondern kommen aus der
    Konfiguration; sonst laufen Erklaerung und Programm auseinander.

    Die Formulierungen sind fachlich, aber nicht anwaltlich geprueft.
--}}
<x-layouts.portal>

    <x-slot name="title">
        {{ __('Datenschutzerklärung') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Wie widileads personenbezogene Daten verarbeitet.') }}
    </x-slot>

    <section class="section">
        <div class="container-portal max-w-3xl">
            <h1 class="text-2xl font-semibold text-zinc-900 mb-6">Datenschutzerklärung</h1>

            <div class="card p-6 space-y-8 text-zinc-600 leading-relaxed">

                <div>
                    <h2 class="font-semibold text-zinc-900 mb-2">1. Verantwortlicher</h2>
                    @if ($company->name())
                        <p>{{ $company->name() }}</p>
                    @endif
                    @if ($company->address())
                        <p class="whitespace-pre-line">{{ $company->address() }}</p>
                    @endif
                    @if ($company->phone())
                        <p>Telefon: {{ $company->phone() }}</p>
                    @endif
                    @if ($company->email())
                        <p>E-Mail: <a class="hover:text-zinc-900" href="mailto:{{ $company->email() }}">{{ $company->email() }}</a></p>
                    @endif
                    @if ($company->isComplete())
                        <p class="mt-2">Weitere Angaben zum Anbieter stehen im <a class="hover:text-zinc-900 underline" href="{{ route('imprint') }}">Impressum</a>.</p>
                    @endif
                    <p class="mt-2">Einen Datenschutzbeauftragten haben wir nicht bestellt; es besteht keine gesetzliche Pflicht dazu. Anliegen zum Datenschutz richten Sie bitte an die oben genannte Adresse.</p>
                </div>

                <div>
                    <h2 class="font-semibold text-zinc-900 mb-2">2. Was dieses Portal tut</h2>
                    <p>Über unsere Portalseiten beantworten Interessenten einen Fragebogen, um ein Angebot für eine Dienstleistung zu erhalten. Aus einer solchen Anfrage entsteht ein Datensatz, den wir Fachbetrieben zum Kauf anbieten. Kauft ein Betrieb die Anfrage, erhält er die Kontaktdaten des Interessenten und nimmt selbst Kontakt auf. Diese Erklärung beschreibt beide Seiten: die Interessenten, die eine Anfrage stellen, und die Käufer, die ein Konto im Portal führen.</p>
                </div>

                <div>
                    <h2 class="font-semibold text-zinc-900 mb-2">3. Daten von Interessenten</h2>
                    <p class="mb-3">Beim Ausfüllen eines Fragebogens verarbeiten wir:</p>
                    <ul class="list-disc pl-5 space-y-1">
                        <li>Ihre Kontaktdaten: Name, Telefonnummer, E-Mail-Adresse und Postleitzahl.</li>
                        <li>Ihre Antworten auf die fachlichen Fragen des Fragebogens.</li>
                        <li>Ihre Einwilligung in die Weitergabe, mit Zeitpunkt.</li>
                        <li>Technische Begleitdaten: Zeitpunkt der Absendung, aufgerufene Seite, verweisende Seite, Browserkennung, Kampagnenkennungen aus der aufgerufenen Adresse sowie ein Prüfwert Ihrer IP-Adresse. Die IP-Adresse selbst speichern wir nicht, sondern nur einen mit einem Geheimnis versehenen Hashwert, der dem Erkennen von Mehrfacheinsendungen und Missbrauch dient.</li>
                    </ul>
                    <p class="mt-3"><span class="font-medium text-zinc-900">Zweck und Rechtsgrundlage:</span> Wir verarbeiten diese Daten, um Ihre Anfrage an einen passenden Fachbetrieb zu vermitteln. Rechtsgrundlage ist Ihre Einwilligung nach Artikel 6 Absatz 1 Buchstabe a der Datenschutz-Grundverordnung, die Sie vor dem Absenden erteilen, sowie die Durchführung vorvertraglicher Maßnahmen nach Artikel 6 Absatz 1 Buchstabe b. Die Einwilligung können Sie jederzeit mit Wirkung für die Zukunft widerrufen; die bis dahin erfolgte Verarbeitung bleibt rechtmäßig.</p>
                    <p class="mt-3">Ohne Angabe der Kontaktdaten können wir Ihre Anfrage nicht vermitteln. Die Angabe ist nicht gesetzlich vorgeschrieben, aber für die Vermittlung erforderlich.</p>
                </div>

                <div>
                    <h2 class="font-semibold text-zinc-900 mb-2">4. Weitergabe an Käufer</h2>
                    <p>Solange eine Anfrage nicht gekauft ist, sehen Fachbetriebe im Marktplatz nur die fachlichen Angaben sowie einen unkenntlich gemachten Auszug der Kontaktdaten. Erst mit dem Kauf erhält der Käufer Ihren Namen, Ihre E-Mail-Adresse und Ihre Telefonnummer. Eine Anfrage kann an mehr als einen Fachbetrieb verkauft werden; ob das der Fall ist, hängt vom jeweiligen Portal ab.</p>
                    <p class="mt-3">Der Käufer ist für die weitere Verarbeitung Ihrer Daten eigenständig verantwortlich. Für seine Kontaktaufnahme und seine eigene Speicherung gilt seine Datenschutzerklärung, nicht diese. Rechtsgrundlage der Weitergabe ist Ihre Einwilligung nach Artikel 6 Absatz 1 Buchstabe a der Datenschutz-Grundverordnung.</p>
                </div>

                <div>
                    <h2 class="font-semibold text-zinc-900 mb-2">5. Telefonische Erreichbarkeitsprüfung</h2>
                    <p>Käufer können den Anruf bei Ihnen aus dem Portal heraus auslösen. Die Verbindung stellt unser Telefonie-Dienstleister Twilio her: Er ruft zuerst den Käufer an und stellt anschließend zu Ihrer Nummer durch. Als Rufnummernanzeige erscheint dabei die zuvor bestätigte Rufnummer des Käufers.</p>
                    <p class="mt-3">Zu jedem Anrufversuch speichern wir die beteiligten Rufnummern, Zeitpunkt und Dauer des Gesprächs, den von Twilio gemeldeten Verbindungsstatus, das Ergebnis der automatischen Anrufbeantwortererkennung sowie die unveränderte Rückmeldung des Dienstleisters. <span class="font-medium text-zinc-900">Gesprächsinhalte werden nicht aufgezeichnet und nicht mitgehört.</span></p>
                    <p class="mt-3">Zweck dieser Protokollierung ist der Nachweis, ob ein Käufer Sie tatsächlich erreicht hat. Davon hängt ab, ob eine Anfrage abgerechnet oder dem Käufer gutgeschrieben wird. Rechtsgrundlage ist unser berechtigtes Interesse an einer nachvollziehbaren Abrechnung nach Artikel 6 Absatz 1 Buchstabe f der Datenschutz-Grundverordnung.</p>
                </div>

                <div>
                    <h2 class="font-semibold text-zinc-900 mb-2">6. Daten von Käufern</h2>
                    <p>Für ein Käuferkonto verarbeiten wir Name, E-Mail-Adresse, Passwort in verschlüsselter Form, Firmen- und Rechnungsangaben, die bestätigte Rufnummer für Anrufe sowie die Vorgänge im Konto: gekaufte Anfragen, Guthabenbewegungen, Anrufversuche, Reklamationen und Rechnungen. Rechtsgrundlage ist die Durchführung des Vertrags nach Artikel 6 Absatz 1 Buchstabe b sowie die Erfüllung steuer- und handelsrechtlicher Pflichten nach Artikel 6 Absatz 1 Buchstabe c der Datenschutz-Grundverordnung.</p>
                </div>

                <div>
                    <h2 class="font-semibold text-zinc-900 mb-2">7. Auftragsverarbeiter und Empfänger</h2>
                    <p class="mb-3">Wir setzen Dienstleister ein, die Daten in unserem Auftrag verarbeiten. Mit ihnen bestehen Verträge zur Auftragsverarbeitung nach Artikel 28 der Datenschutz-Grundverordnung.</p>
                    <ul class="list-disc pl-5 space-y-1">
                        <li><span class="font-medium text-zinc-900">Twilio</span> – Herstellung der Telefonverbindung zwischen Käufer und Interessent sowie Protokollierung der Verbindungsdaten.</li>
                        <li><span class="font-medium text-zinc-900">Zahlungsdienstleister</span> – Abwicklung von Guthabenaufladungen und Rechnungen. Je nach gewähltem Zahlweg sind das Stripe, Paddle oder Lemon Squeezy. Zahlungsdaten wie Kartennummern erreichen unsere Server nicht; sie werden unmittelbar beim Zahlungsdienstleister eingegeben.</li>
                        <li><span class="font-medium text-zinc-900">Hosting- und E-Mail-Dienstleister</span> – Betrieb der Server und Versand der Benachrichtigungen an Interessenten und Käufer.</li>
                    </ul>
                    <p class="mt-3">Soweit dabei Daten in Länder außerhalb der Europäischen Union übermittelt werden, stützt sich die Übermittlung auf die Standardvertragsklauseln der Europäischen Kommission.</p>
                    <p class="mt-3">Darüber hinaus geben wir Daten weiter, wenn wir gesetzlich dazu verpflichtet sind, etwa gegenüber Finanzbehörden oder Strafverfolgungsbehörden.</p>
                </div>

                <div>
                    <h2 class="font-semibold text-zinc-900 mb-2">8. Server-Protokolle und Cookies</h2>
                    <p>Beim Aufruf unserer Seiten fallen technische Protokolldaten an, die dem sicheren Betrieb dienen und nach kurzer Zeit gelöscht werden. Wir setzen Cookies ein, soweit sie für den Betrieb erforderlich sind: für die Anmeldung am Konto, für den Schutz von Formularen vor missbräuchlicher Nutzung und für das Fortsetzen eines begonnenen Fragebogens. Rechtsgrundlage ist Paragraf 25 Absatz 2 des Telekommunikation-Digitale-Dienste-Datenschutz-Gesetzes in Verbindung mit Artikel 6 Absatz 1 Buchstabe f der Datenschutz-Grundverordnung. Cookies zu Werbezwecken setzen wir ohne Ihre Einwilligung nicht.</p>
                </div>

                <div>
                    <h2 class="font-semibold text-zinc-900 mb-2">9. Aufbewahrung und Löschung</h2>
                    <p>Eine Anfrage, die nie verkauft wurde, verfällt nach Ablauf der Aufbewahrungsfrist von {{ (int) config('funnel.lead.retention_days') }} Tagen. Nach Ablauf dieser Frist entfernen wir aus allen abgeschlossenen Anfragen den Personenbezug: Telefonnummer, E-Mail-Adresse und die Antworten auf die Kontaktfragen werden gelöscht. Was bleibt, sind Angaben ohne Personenbezug, die wir für Auswertungen und für die Nachvollziehbarkeit abgeschlossener Abrechnungen benötigen, etwa Zeitpunkt, Preis und Zustand der Anfrage. Dieser Lauf findet täglich statt.</p>
                    <p class="mt-3">Rechnungen und die zugehörigen Buchungsdaten bewahren wir nach handels- und steuerrechtlichen Vorgaben zehn Jahre auf. Käuferkonten löschen wir nach Beendigung des Vertragsverhältnisses, soweit keine Aufbewahrungspflicht entgegensteht.</p>
                </div>

                <div>
                    <h2 class="font-semibold text-zinc-900 mb-2">10. Ihre Rechte</h2>
                    <p class="mb-3">Sie haben uns gegenüber folgende Rechte hinsichtlich Ihrer personenbezogenen Daten:</p>
                    <ul class="list-disc pl-5 space-y-1">
                        <li>Auskunft über die gespeicherten Daten (Artikel 15).</li>
                        <li>Berichtigung unrichtiger Daten (Artikel 16).</li>
                        <li>Löschung (Artikel 17). Wir entfernen dabei den Personenbezug; Preis- und Zeitangaben abgeschlossener Vorgänge bleiben ohne Bezug zu Ihnen bestehen, weil wir sie für die Buchhaltung benötigen.</li>
                        <li>Einschränkung der Verarbeitung (Artikel 18).</li>
                        <li>Datenübertragbarkeit in einem maschinenlesbaren Format (Artikel 20).</li>
                        <li>Widerspruch gegen eine Verarbeitung, die auf berechtigtem Interesse beruht (Artikel 21).</li>
                        <li>Widerruf einer erteilten Einwilligung mit Wirkung für die Zukunft (Artikel 7 Absatz 3).</li>
                    </ul>
                    <p class="mt-3">Für Auskunft und Löschung genügt eine formlose Nachricht an die oben genannte Kontaktadresse unter Angabe der E-Mail-Adresse, mit der Sie die Anfrage gestellt haben. Beachten Sie: Wurde Ihre Anfrage bereits an einen Fachbetrieb verkauft, verfügt dieser über eine eigene Kopie Ihrer Daten. Ihr Löschverlangen richten Sie in diesem Fall zusätzlich an den Betrieb, der Sie kontaktiert hat; auf Wunsch nennen wir Ihnen, an wen Ihre Anfrage weitergegeben wurde.</p>
                    <p class="mt-3">Unabhängig davon steht Ihnen ein Beschwerderecht bei einer Datenschutz-Aufsichtsbehörde zu, in der Regel bei der Behörde Ihres gewöhnlichen Aufenthaltsorts.</p>
                </div>

                <div>
                    <h2 class="font-semibold text-zinc-900 mb-2">11. Automatisierte Entscheidungen</h2>
                    <p>Eine Anfrage erhält anhand Ihrer Antworten eine Punktzahl, aus der sich das angezeigte Ergebnis und der Preis der Anfrage im Marktplatz ergeben. Eine automatisierte Entscheidung mit rechtlicher Wirkung Ihnen gegenüber im Sinne von Artikel 22 der Datenschutz-Grundverordnung ist damit nicht verbunden.</p>
                </div>

                <div>
                    <h2 class="font-semibold text-zinc-900 mb-2">12. Änderungen dieser Erklärung</h2>
                    <p>Wir passen diese Erklärung an, wenn sich der Betrieb des Portals oder die Rechtslage ändert. Es gilt jeweils die hier veröffentlichte Fassung.</p>
                </div>

            </div>
        </div>
    </section>

</x-layouts.portal>
