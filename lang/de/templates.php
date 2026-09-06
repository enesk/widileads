<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Funnel-Vorlagen (FB-019)
    |--------------------------------------------------------------------------
    |
    | Beschriftungen der Vorlagen unter database/templates und der Import-Seite
    | im Admin-Panel.
    |
    */

    'nav_label' => 'Funnel-Vorlagen',
    'heading' => 'Funnel-Vorlagen',
    'section_heading' => 'Fertige Strecken uebernehmen',
    'section_body' => 'Eine Vorlage legt einen vollstaendigen Funnel als Entwurf an - mit Schritten, Fragen, Punkten und Ergebnis-Screens. Veroeffentlicht wird er erst, wenn du ihn geprueft hast.',
    'section_hint' => 'Wird dieselbe Vorlage mehrfach angelegt, bekommt jeder Funnel einen eigenen Slug und einen eigenen oeffentlichen Token.',

    'names' => [
        'pfotencheck' => 'Pfotencheck (Tierkrankenversicherung)',
    ],

    'create' => [
        'action' => 'Vorlage anlegen',
        'heading' => 'Vorlage als Funnel anlegen',
        'description' => 'Der Funnel entsteht als Entwurf im gewaehlten Workspace.',
        'submit' => 'Anlegen',
        'template' => 'Vorlage',
        'tenant' => 'Workspace',
        'tenant_helper' => 'Nur Betreiber-Workspaces koennen Funnels besitzen.',
        'done' => 'Funnel ":name" wurde als Entwurf angelegt.',
        'no_templates' => 'Unter database/templates liegt keine Vorlage.',
    ],

    'errors' => [
        'not_found' => 'Zur Vorlage ":key" gibt es keine Datei unter database/templates.',
    ],

];
