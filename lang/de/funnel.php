<?php

return [

    'destructive' => [
        'description' => 'Dieser Befehl ist im Funnel Builder dauerhaft gesperrt.',
        'blocked' => 'Der Befehl ":command" ist im Funnel Builder gesperrt und wurde abgebrochen. Es wurden keine Daten geloescht.',
        'hint' => 'Datenvernichtende Befehle sind in JEDER Umgebung gesperrt - auch mit --force. Nur die Testsuite darf sie ausfuehren (APP_ENV=testing und FUNNEL_ALLOW_DESTRUCTIVE=1). Nutze stattdessen "php artisan migrate".',
    ],

];
