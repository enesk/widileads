<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Funnel templates (FB-019)
    |--------------------------------------------------------------------------
    |
    | Labels for the templates under database/templates and for the import page
    | in the admin panel.
    |
    */

    'nav_label' => 'Funnel templates',
    'heading' => 'Funnel templates',
    'section_heading' => 'Start from a finished funnel',
    'section_body' => 'A template creates a complete funnel as a draft - steps, questions, scores and result screens included. It is published only once you have reviewed it.',
    'section_hint' => 'Creating the same template more than once gives every funnel its own slug and its own public token.',

    'names' => [
        'pfotencheck' => 'Pfotencheck (pet health insurance)',
    ],

    'create' => [
        'action' => 'Create from template',
        'heading' => 'Create a funnel from a template',
        'description' => 'The funnel is created as a draft in the selected workspace.',
        'submit' => 'Create',
        'template' => 'Template',
        'tenant' => 'Workspace',
        'tenant_helper' => 'Only operator workspaces can own funnels.',
        'done' => 'Funnel ":name" was created as a draft.',
        'no_templates' => 'There is no template under database/templates.',
    ],

    'errors' => [
        'not_found' => 'There is no file under database/templates for template ":key".',
    ],

];
