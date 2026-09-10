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
    'section_heading' => 'Fertige Strecken übernehmen',
    'section_body' => 'Eine Vorlage legt einen vollständigen Funnel als Entwurf an - mit Schritten, Fragen, Punkten und Ergebnis-Screens. Veröffentlicht wird er erst, wenn du ihn geprüft hast.',
    'section_hint' => 'Wird dieselbe Vorlage mehrfach angelegt, bekommt jeder Funnel einen eigenen Slug und einen eigenen öffentlichen Token.',

    'names' => [
        'pfotencheck' => 'Pfotencheck (Tierkrankenversicherung)',
    ],

    'create' => [
        'action' => 'Vorlage anlegen',
        'heading' => 'Vorlage als Funnel anlegen',
        'description' => 'Der Funnel entsteht als Entwurf im gewählten Workspace.',
        'submit' => 'Anlegen',
        'template' => 'Vorlage',
        'tenant' => 'Workspace',
        'tenant_helper' => 'Nur Betreiber-Workspaces können Funnels besitzen.',
        'done' => 'Funnel ":name" wurde als Entwurf angelegt.',
        'no_templates' => 'Unter database/templates liegt keine Vorlage.',
    ],

    'errors' => [
        'not_found' => 'Zur Vorlage ":key" gibt es keine Datei unter database/templates.',
    ],

];
