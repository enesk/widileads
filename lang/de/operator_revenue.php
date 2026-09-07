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
    'revenue' => 'Umsatz',
    'refunds' => 'Gutschriften',
    'net' => 'Netto',
    'by_funnel' => 'Je Funnel',
    'by_buyer' => 'Je Kaeufer',
    'without_funnel' => 'Ohne Funnel',
    'empty' => 'Im gewaehlten Zeitraum wurde kein Lead verkauft.',

];
