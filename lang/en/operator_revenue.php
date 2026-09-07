<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Operator revenue overview (FB-072)
    |--------------------------------------------------------------------------
    |
    | Die Datei heisst bewusst nicht revenue.php: Der Bestandscode uebersetzt
    | den Navigationstitel ueber __('Revenue'), und auf einem Dateisystem ohne
    | Gross-/Kleinschreibung wuerde Laravel daraus die Gruppe revenue.php
    | aufloesen und ein Array statt eines Textes liefern.
    */

    'heading' => 'Revenue',
    'nav_label' => 'Revenue',
    'from' => 'From',
    'until' => 'Until',
    'sold' => 'Leads sold',
    'revenue' => 'Revenue',
    'refunds' => 'Refunds',
    'net' => 'Net',
    'by_funnel' => 'By funnel',
    'by_buyer' => 'By buyer',
    'without_funnel' => 'Without funnel',
    'empty' => 'No lead was sold in the selected period.',

];
