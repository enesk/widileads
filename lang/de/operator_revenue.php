<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Umsatzuebersicht des Betreibers (FB-072)
    |--------------------------------------------------------------------------
    |
    | Die Datei heisst bewusst nicht revenue.php: Der Bestandscode uebersetzt
    | den Navigationstitel ueber __('Revenue'), und auf einem Dateisystem ohne
    | Gross-/Kleinschreibung wuerde Laravel daraus die Gruppe revenue.php
    | aufloesen und ein Array statt eines Textes liefern.
    */

    'heading' => 'Umsatz',
    'nav_label' => 'Umsatz',
    'from' => 'Von',
    'until' => 'Bis',
    'sold' => 'Verkaufte Leads',
    'revenue' => 'Einnahmen nach Provision',
    'refunds' => 'Gutschriften',
    'net' => 'Nach Gutschriften',
    'by_funnel' => 'Je Funnel',
    'by_buyer' => 'Je Käufer',
    'without_funnel' => 'Ohne Funnel',
    'empty' => 'Im gewählten Zeitraum wurde kein Lead verkauft.',

];
