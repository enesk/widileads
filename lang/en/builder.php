<?php

/**
 * Funnel builder (FB-015).
 *
 * Its own topic file instead of a block in funnel.php, so two tickets running
 * in parallel can never touch the same file.
 */
return [
    'title' => 'Builder: :funnel',
    'steps' => 'Steps',
    'add_step' => 'Step',
    'new_step' => 'New step',
    'step_title' => 'Step title',
    'no_steps' => 'No step created yet.',
    'select_step' => 'Pick a step on the left.',
    'confirm_delete_step' => 'Delete this step and its questions?',
    'questions' => 'Questions',
    'add_question' => 'Question',
    'no_questions' => 'This step has no question yet.',
    'select_question' => 'Pick a question in the middle.',
    'confirm_delete_question' => 'Delete this question?',
    'properties' => 'Properties',
    'type' => 'Question type',
    'label' => 'Label',
    'field_key' => 'Field key',
    'field_key_hint' => 'The answer is stored under this name on the lead. It is normalised on save.',
    'help_text' => 'Help text',
    'required' => 'Required',
    'rendered_by' => 'Rendered by the :component component.',
    'options' => 'Answer options',
    'add_option' => 'Option',
    'new_option' => 'Option :position',
    'no_options' => 'This question type has no options, or none has been created yet.',
    'confirm_delete_option' => 'Delete this option?',
    'delete' => 'Delete',
    'drag' => 'Drag to reorder',
    'reload' => 'Load the other version',
    'concurrent_edit' => 'The record was changed elsewhere in the meantime. Your input was not saved so the other change is not lost.',

    // FB-017: theme editor
    'theme' => [
        'title' => 'Appearance: :funnel',
        'primary_color' => 'Primary colour',
        'secondary_color' => 'Secondary colour',
        'background_color' => 'Background',
        'text_color' => 'Text colour',
        'font_label' => 'Font',
        'progress_label' => 'Progress indicator',
        'border_radius' => 'Corner radius',
        'logo_path' => 'Logo',
        'logo_placeholder' => 'Path in public storage, e.g. logos/pfotencheck.svg',
        'buttons' => 'Button labels',
        'buttons_hint' => 'Leave empty for the default.',
        'save' => 'Save',
        'saved' => 'Saved.',
        'publish_hint' => 'The appearance only reaches end customers with the next publication - the public funnel reads published versions only.',
        'preview' => 'Preview',
        'preview_hint' => 'Shows the state in the form, not the saved one.',
        'preview_question' => 'Which animal would you like to insure?',
        'preview_help' => 'Example rendering of one step with your colours and fonts.',
        'preview_option_one' => 'Dog',
        'preview_option_two' => 'Cat',
        'preview_option_three' => 'Another animal',
        'default_next' => 'Next',
        'default_back' => 'Back',
        'default_submit' => 'Submit',
        'font' => [
            'system' => 'System font',
            'inter' => 'Inter',
            'roboto' => 'Roboto',
            'open_sans' => 'Open Sans',
        ],
        'progress' => [
            'bar' => 'Bar',
            'steps' => 'Steps',
            'none' => 'No indicator',
        ],
    ],

];
