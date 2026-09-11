<?php

/*
|--------------------------------------------------------------------------
| Telefonische Kontaktaufnahme (FB-080 ff.)
|--------------------------------------------------------------------------
|
| Eigene Sprachdatei je Thema, angesprochen als __('call.<schluessel>').
| Die weiteren Tickets des Anrufnachweises (FB-081 bis FB-085) ergaenzen hier
| ihre eigenen Bereiche neben 'caller_id'.
|
*/

return [

    'caller_id' => [

        'heading' => 'Eigene Rufnummer',
        'nav_label' => 'Rufnummer',
        'description' => 'Beim Angerufenen erscheint Ihre eigene Rufnummer. Dafür muss sie einmalig bestätigt werden.',

        'phone_number' => 'Rufnummer',
        'phone_number_helper' => 'Mit Ländervorwahl, zum Beispiel +49 30 1234567. Ohne Vorwahl wird eine deutsche Nummer angenommen.',

        'current_heading' => 'Stand der Bestätigung',
        'status_label' => 'Stand',
        'code_label' => 'Angesagter Code',
        'code_hint' => 'Sie werden angerufen und hören diesen Code. Nehmen Sie den Anruf an und folgen Sie der Ansage.',

        'submit' => 'Bestätigung anfordern',

        'requested' => 'Bestätigung angefordert',
        'requested_body' => 'Sie werden gleich angerufen. Der angesagte Code steht auf dieser Seite.',
        'invalid_number' => 'Das ist keine wählbare Rufnummer.',
        'provider_failed' => 'Die Bestätigung konnte nicht angefordert werden. Bitte später erneut versuchen.',

        // Portal-Fassung (Portal Phase 1). Eigene Bausteine, weil der Ablauf
        // dort in vier Schritten laeuft und die Oberflaeche duzt.
        'portal' => [
            'back' => 'Rufnummern',
            'heading' => 'Rufnummer bestätigen',
            'description' => 'Über diese Nummer verbinden wir dich mit den Anfragenden. Wir rufen dich einmal an, um sie zu bestätigen.',

            'steps' => [
                'enter' => 'Nummer eingeben',
                'calling' => 'Anruf annehmen',
                'code' => 'Code eingeben',
            ],

            'label' => 'Bezeichnung',
            'label_placeholder' => 'z. B. Büro, Handy Enes',
            'label_hint' => 'Nur für deine Übersicht.',
            'number' => 'Rufnummer',
            'number_placeholder' => '172 1234567',
            'number_hint' => 'Ohne führende 0. Mobil oder Festnetz, beides geht.',
            'country' => 'Ländervorwahl',
            'explainer' => 'Gleich klingelt dein Telefon. Eine Ansage nennt dir einen 6-stelligen Code, den du hier eingibst. Der Anruf ist kostenlos und dauert unter einer Minute.',
            'cancel' => 'Abbrechen',
            'start' => 'Jetzt anrufen lassen',

            'calling_heading' => 'Dein Telefon klingelt gleich',
            'calling_text' => 'Wir rufen :number an. Nimm ab und hör dir den Code an.',
            'calling_spinner' => 'Anruf wird aufgebaut …',
            'calling_code' => 'Erwarteter Code: :code',
            'have_code' => 'Ich habe den Code',
            'other_number' => 'Andere Nummer',
            'no_call' => 'Kein Anruf nach 60 Sekunden? Dann kannst du es erneut versuchen.',

            'code_heading' => 'Code eingeben',
            'code_text' => 'Den 6-stelligen Code aus der Ansage. Du kannst ihn dir per Taste 1 wiederholen lassen.',
            'code_field' => 'Bestätigungscode',
            'code_digit' => 'Ziffer :position',
            'code_wrong' => 'Der Code stimmt nicht. Noch :count Versuche.',
            'code_exhausted' => 'Der Code stimmt nicht. Fordere den Anruf erneut an.',
            'code_ttl' => 'Der Code gilt :minutes Minuten.',
            'call_again' => 'Erneut anrufen lassen',
            'confirm' => 'Nummer bestätigen',

            'done_heading' => 'Nummer bestätigt',
            'done_text' => ':number ist jetzt für Anrufe über :app freigeschaltet.',
            'make_default' => 'Als Standardnummer verwenden',
            'to_marketplace' => 'Zum Marktplatz',
            'to_numbers' => 'Zu meinen Rufnummern',

            'why_heading' => 'Warum wir anrufen',
            'why_one' => 'Bei „Jetzt anrufen" klingelt zuerst dein Telefon, dann verbinden wir dich mit dem Anfragenden.',
            'why_two' => 'Der Anfragende sieht unsere Portalnummer, nicht deine.',
            'why_three' => 'Nur bestätigte Nummern können Anrufe auslösen – so kann niemand auf deine Kosten telefonieren.',

            'verified_heading' => 'Bereits bestätigt',
            'default_label' => 'Standard',
            'trouble_heading' => 'Probleme?',
            'trouble_text' => 'Anruf kommt nicht an: Prüf, ob die Nummer unterdrückte Anrufe blockiert. Unsere Nummer ist :number.',
            'support' => 'Support schreiben',

            'failed' => 'Die Bestätigung ist fehlgeschlagen. Prüfe die Nummer und versuche es erneut.',
        ],

        'status' => [
            'pending' => 'Bestätigung läuft',
            'verified' => 'Bestätigt',
            'expired' => 'Abgelaufen',
            'failed' => 'Fehlgeschlagen',
        ],

    ],

    'attempt' => [

        'action' => 'Lead anrufen',
        'submit' => 'Anruf starten',
        'help' => 'Wir rufen zuerst Sie auf Ihrer bestätigten Rufnummer an und stellen Sie danach zum Lead durch. Beim Lead erscheint Ihre eigene bestätigte Rufnummer, ein Rückruf erreicht also direkt Sie.',

        'started' => 'Anruf gestartet',
        'started_body' => 'Ihr Telefon klingelt gleich. Nehmen Sie ab, dann wird zum Lead durchgestellt.',

        'status' => [
            'queued' => 'Wird gewählt',
            'ringing' => 'Es klingelt',
            'in_progress' => 'Gespräch läuft',
            'completed' => 'Beendet',
            'no_answer' => 'Keine Antwort',
            'busy' => 'Besetzt',
            'failed' => 'Fehlgeschlagen',
            'canceled' => 'Abgebrochen',
        ],

        'outcome' => [
            'answered' => 'Erreicht',
            'failed_valid' => 'Nicht erreicht',
            'failed_ignored' => 'Zählt nicht',
        ],

        'errors' => [
            'caller_id_missing' => 'Bestätigen Sie zuerst Ihre eigene Rufnummer.',
            'lead_number_missing' => 'Zu diesem Lead liegt keine Rufnummer vor.',
            'foreign_purchase' => 'Dieser Kauf gehört zu einem anderen Workspace.',
            'not_configured' => 'Die Telefonanbindung ist noch nicht eingerichtet.',
            'lead_resolved' => 'Die Erreichbarkeit dieses Leads ist bereits entschieden.',
            'too_soon' => 'Nächster gültiger Versuch erst ab :time Uhr möglich.',
            'already_running' => 'Zu diesem Lead läuft bereits ein Anruf.',
            'provider_failed' => 'Der Anruf konnte nicht gestartet werden. Bitte später erneut versuchen.',
        ],

    ],

    'panel' => [

        'heading' => 'Anruf und Erreichbarkeit',
        'action' => 'Jetzt anrufen',
        'calling' => 'Wir rufen Sie unter :number an — bitte abnehmen.',

        'history' => 'Versuche',
        'counter' => 'Fehlversuche :count von :required',
        'not_counted' => 'nicht gezählt',
        'no_attempts' => 'Zu diesem Lead wurde noch kein Anruf gestartet.',

        'rule_hint' => 'Ein Lead gilt erst nach :attempts erfolglosen Versuchen an :days verschiedenen Tagen (mindestens :hours Stunden Abstand) als nicht erreichbar. Frist: :deadline.',

        'badge' => [
            'open' => 'Offen',
            'billable' => 'Erreicht — berechnet',
            'unreachable' => 'Nicht erreichbar',
        ],

        'result' => [
            'running' => 'Anruf läuft',
            'talk' => 'Gespräch :duration min',
            'voicemail' => 'Mailbox',
            'no_answer' => 'Nicht abgenommen',
            'busy' => 'Besetzt',
            'failed' => 'Fehlgeschlagen',
            'canceled' => 'Abgebrochen',
        ],

        'ignored' => [
            'too_soon' => 'Zu kurz nach dem letzten Versuch',
            'lead_closed' => 'Lead war bereits entschieden',
            'buyer_no_answer' => 'Sie haben selbst nicht abgenommen',
            'twilio_error' => 'Der Anruf kam nicht zustande',
            'other' => 'Zählt nicht mit',
        ],

        'list' => [
            'contact_status' => 'Erreichbarkeit',
            'attempts' => 'Fehlversuche',
        ],

    ],

    'bridge' => [
        'connecting' => 'Sie werden mit dem Lead verbunden.',
        'closed' => 'Dieser Lead ist bereits abgeschlossen.',
    ],

    'reachability' => [

        'heading' => 'Erreichbarkeit der Leads',
        'nav_label' => 'Erreichbarkeit',
        'description' => 'Anteil der Leads, die ein Käufer nicht ans Telefon bekommen hat. Der Durchschnitt aller Käufer liegt bei :average %; hervorgehoben wird, wer mehr als :points Prozentpunkte darüber liegt.',
        'hint' => 'Die Quote bezieht sich auf entschiedene Leads (erreicht oder nicht erreichbar). Leads mit laufender Frist stehen unter "Offen" und zählen nicht mit. Auffällig heißt nicht schuldig — die Übersicht sagt nur, wo Hinsehen sich lohnt.',
        'empty' => 'Für diesen Zeitraum liegen keine gekauften Leads vor.',

        'from' => 'Entschieden ab',
        'to' => 'Entschieden bis',
        'export' => 'Als CSV herunterladen',

        'buyer' => 'Käufer',
        'unknown_buyer' => 'Unbekannter Käufer',
        'leads_total' => 'Leads gesamt',
        'billable' => 'Erreicht',
        'unreachable' => 'Nicht erreichbar',
        'open' => 'Offen',
        'rate' => 'Quote',
        'deviation' => 'Abweichung (PP)',
        'flagged' => 'Auffällig',
        'total' => 'Alle Käufer',

        'csv' => [
            'buyer' => 'Käufer',
            'leads_total' => 'Leads gesamt',
            'billable' => 'Erreicht',
            'unreachable' => 'Nicht erreichbar',
            'open' => 'Offen',
            'rate' => 'Quote in Prozent',
            'deviation' => 'Abweichung in Prozentpunkten',
            'flagged' => 'Auffällig',
            'average' => 'Alle Käufer',
        ],

        'detail' => [
            'heading' => 'Lead #:id',
            'state' => 'Stand',
            'contact_status' => 'Erreichbarkeit',
            'resolved_by' => 'Entschieden durch',
            'delivered_at' => 'Ausgeliefert am',
            'deadline_at' => 'Frist bis',
            'resolved_at' => 'Entschieden am',

            'attempts' => 'Versuchsverlauf',
            'attempts_hint' => 'Alle Anrufversuche zu diesem Lead, neueste zuerst. Reine Ansicht — hier lässt sich nichts ändern.',
            'no_attempts' => 'Zu diesem Lead wurde noch kein Anruf gestartet.',

            'when' => 'Zeitpunkt',
            'buyer' => 'Käufer',
            'status' => 'Stand',
            'dial_status' => 'Lead-Leg (dial_status)',
            'answered_by' => 'Erkennung (answered_by)',
            'duration' => 'Dauer',
            'seconds' => ':seconds s',
            'outcome' => 'Bewertung',
            'ignore_reason' => 'Verworfen weil',
            'call_sid' => 'CallSid',
            'dial_sid' => 'Dial-CallSid',
            'raw_payload' => 'Rohmeldung von Twilio',
            'no_payload' => 'Keine Rohmeldung gespeichert.',
        ],

    ],

    'reminder' => [

        'mail' => [
            'subject' => 'Ihre Frist für einen Lead endet morgen',
            'heading' => 'Frist endet in weniger als 24 Stunden',
            'reference_label' => 'Referenz',
            'intro' => 'Für diesen Lead läuft die Frist zur telefonischen Kontaktaufnahme in weniger als 24 Stunden ab. Bis dahin können Sie ihn im Portal anrufen.',
            'deadline_at' => 'Frist endet am :date um :time Uhr.',
            'attempts_label' => 'Dokumentierte Versuche bisher',
            'attempts_value' => ':attempts von :required',
            'warning' => 'Ohne :required dokumentierte Versuche gilt der Lead als erreichbar und wird berechnet.',
            'cta' => 'Lead im Portal öffnen',
        ],

    ],

    'contact_status' => [
        'open' => 'Offen',
        'billable' => 'Erreicht',
        'unreachable' => 'Nicht erreichbar',
    ],

    'phone_release' => [
        'pending' => 'Die vollständige Rufnummer wird freigegeben, sobald der Lead abgerechnet ist. Bis dahin erreichst du ihn über "Anrufen".',
        'unreachable' => 'Dieser Lead gilt als nicht erreichbar. Die Rufnummer wird nicht mehr freigegeben.',
    ],

    'resolution' => [
        'answered' => 'Anruf angenommen',
        'three_attempts' => 'Versuche ausgeschöpft',
        'deadline' => 'Frist abgelaufen',
    ],

];
