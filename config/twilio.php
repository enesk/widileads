<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Twilio (FB-082)
    |--------------------------------------------------------------------------
    |
    | Zugangsdaten und Portal-Nummer der Twilio-Anbindung. Der Container loest
    | `Twilio\Rest\Client` als Singleton ausschliesslich aus diesen Werten auf
    | (siehe AppServiceProvider). Kein Service darf Zugangsdaten selbst aus der
    | Umgebung lesen -- immer ueber config('twilio.*').
    |
    | Hinweis: `config/services.php` fuehrt denselben `twilio`-Block fuer die
    | mitgelieferte SMS-Verifikation von SaaSykit. Beide lesen dieselben
    | Env-Keys, damit es genau eine Quelle in der .env gibt.
    |
    */

    // Account SID des Twilio-Accounts (beginnt mit "AC").
    'account_sid' => env('TWILIO_SID'),

    // Auth Token des Twilio-Accounts.
    'auth_token' => env('TWILIO_TOKEN'),

    // Die Portal-Nummer in E.164. Sie ist die Absendernummer jedes
    // Click-to-Call-Legs und zugleich die maskierte Caller-ID, die der Lead
    // sieht, solange die Rufnummernfreigabe nicht erfolgt ist.
    'from_number' => env('TWILIO_FROM'),

];
