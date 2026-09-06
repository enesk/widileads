<?php

return [

    'destructive' => [
        'description' => 'This command is permanently blocked in the Funnel Builder.',
        'blocked' => 'The command ":command" is blocked in the Funnel Builder and was aborted. No data was deleted.',
        'hint' => 'Destructive commands are blocked in EVERY environment - even with --force. Only the test suite may run them (APP_ENV=testing and FUNNEL_ALLOW_DESTRUCTIVE=1). Use "php artisan migrate" instead.',
    ],

    'tenant_type' => [
        'label' => 'Workspace type',
        'helper' => 'Operators build funnels, buyers purchase the resulting leads. The type cannot be changed later.',
        'operator' => 'Operator',
        'buyer' => 'Buyer',
        'no_tenant' => 'This page requires an active workspace.',
        'forbidden' => 'This page is not available for workspaces of type ":type".',
    ],

    'audit' => [

        'resource' => [
            'label' => 'Audit entry',
            'plural_label' => 'Audit log',
            'empty_heading' => 'No audit entries yet',
            'empty_description' => 'Security relevant events show up here as soon as they happen.',
        ],

        'fields' => [
            'created_at' => 'Timestamp',
            'action' => 'Action',
            'tenant' => 'Tenant',
            'user' => 'Acting user',
            'subject' => 'Affected record',
            'subject_type' => 'Type',
            'subject_id' => 'Identifier',
            'payload' => 'Details',
            'ip_hash' => 'IP hash (SHA-256)',
        ],

        'filters' => [
            'action' => 'Action',
            'tenant' => 'Tenant',
            'from' => 'From',
            'until' => 'Until',
        ],

        'hints' => [
            'read_only' => 'Audit entries are immutable: they cannot be created, edited or deleted.',
            'ip_hash' => 'The IP address is never stored, only its salted SHA-256 hash.',
        ],

        'actions' => [
            'user_logged_in' => 'Login',
            'tenant_switched' => 'Tenant switched',
            'role_assigned' => 'Role assigned',
            'role_revoked' => 'Role revoked',
            'api_token_created' => 'API token created',
            'api_token_deleted' => 'API token deleted',
            'data_exported' => 'Data exported',
            'data_erased' => 'Data anonymised (erasure request)',
            'lead_purchased' => 'Lead purchased',
            'lead_state_forced' => 'Lead state forced',
        ],

        'errors' => [
            'not_updatable' => 'An audit entry cannot be changed once it has been created.',
            'not_deletable' => 'An audit entry cannot be deleted.',
        ],

    ],

    'api_token' => [
        'heading' => 'API access',
        'nav_label' => 'API access',
        'description' => 'Tokens belong to this workspace and can only reach its data.',
        'empty' => 'No API access created yet.',
        'name' => 'Label',
        'name_placeholder' => 'e.g. Website embed',
        'name_helper' => 'What is this token used for? For example "Website embed" or "CRM integration".',
        'abilities' => 'Abilities',
        'abilities_helper' => 'Only the abilities selected here are possible with this token.',
        'last_used_at' => 'Last used',
        'never_used' => 'Never used',
        'created_at' => 'Created at',
        'create' => 'Create token',
        'created' => 'Token has been created.',
        'revoke' => 'Revoke',
        'revoke_confirm' => 'The token becomes invalid immediately. Applications using it will lose access.',
        'revoked' => 'Token has been revoked.',
        'revoke_failed' => 'Token could not be revoked.',
        'plain_text_heading' => 'Token visible only now',
        'plain_text_hint' => 'Copy the token now. Only a hash is stored, so it cannot be shown again later.',
        'plain_text_dismiss' => 'Understood, hide it',
        'limit_reached' => 'A workspace can have at most :limit valid tokens at a time. Revoke an existing token first.',
        'no_tenant_token' => 'The token used does not belong to any workspace.',
        'ability' => [
            'funnels_read' => 'Read funnels',
            'funnels_write' => 'Write funnels',
            'leads_read' => 'Read leads',
            'webhooks_manage' => 'Manage webhooks',
        ],
    ],

    'status' => [
        'draft' => 'Draft',
        'published' => 'Published',
        'archived' => 'Archived',
    ],

    'condition_operator' => [
        'equals' => 'equals',
        'not_equals' => 'does not equal',
        'in' => 'is one of',
        'gt' => 'is greater than',
        'lt' => 'is less than',
        'contains' => 'contains',
        'answered' => 'was answered',
        'score_gte' => 'score at least',
    ],

    'lead' => [

        // States of the lead lifecycle (App\Constants\LeadState).
        'state' => [
            'neu' => 'New',
            'verfuegbar' => 'Available',
            'reserviert' => 'Reserved',
            'verkauft' => 'Sold',
            'erreicht' => 'Reached',
            'unerreichbar' => 'Unreachable',
            'ungueltig' => 'Invalid',
            'abgelaufen' => 'Expired',
        ],

        // Reasons for a state change (App\Constants\LeadTransitionReason).
        'reason' => [
            'screening_passed' => 'Screening passed',
            'duplicate' => 'Duplicate',
            'implausible_contact' => 'Contact details not plausible',
            'spam' => 'Detected as spam',
            'reserved_by_buyer' => 'Reserved by buyer',
            'reservation_expired' => 'Reservation expired',
            'reservation_released' => 'Reservation released',
            'purchased' => 'Purchased',
            'call_answered' => 'Call answered',
            'call_attempts_exhausted' => 'Call attempts exhausted',
            'complaint_approved' => 'Complaint approved',
            'complaint_period_elapsed' => 'Complaint period elapsed',
            'retention_elapsed' => 'Retention period reached',
            'manual_override' => 'Set manually',
        ],

        'errors' => [
            'illegal_transition' => 'A lead in state ":from" cannot move to ":to". Possible would be: :allowed.',
            'no_transition_allowed' => 'no further change (final state)',
            'concurrent_transition' => 'When moving to ":to" the lead was no longer in the expected state ":expected" but in ":actual". Another process was faster.',
            'log_not_updatable' => 'An entry in the state log cannot be changed after it has been created.',
            'log_not_deletable' => 'An entry in the state log cannot be deleted.',
            'justification_too_short' => 'The justification must be at least :min characters long.',
            'force_state_forbidden' => 'Only operator administrators may set the state of a lead manually.',
        ],

        // Lead overview in the admin panel (FB-036).
        'resource' => [
            'label' => 'Lead',
            'plural_label' => 'Leads',
            'empty_heading' => 'No leads yet',
            'empty_description' => 'Leads are created from completed funnel submissions.',
        ],

        'fields' => [
            'id' => 'ID',
            'tenant' => 'Tenant',
            'lead_state' => 'State',
            'settled_price' => 'Settled price',
            'created_at' => 'Received at',
            'anonymized_at' => 'Anonymised at',
        ],

        // Manual state override (FB-036).
        'force_state' => [
            'action' => 'Set state',
            'heading' => 'Set the state manually',
            'description' => 'The change is recorded in the state log and in the audit log. Only states that are allowed from the current one are offered.',
            'target' => 'New state',
            'justification' => 'Justification',
            'justification_helper' => 'At least :min characters. Whoever reviews this later reads exactly this text.',
            'submit' => 'Set state',
            'done' => 'The lead is now in state ":state".',
        ],

        // Retention period (FB-037).
        'retention' => [
            'summary' => 'Retention run (:days days): :expired lead(s) expired, :anonymized lead(s) anonymised.',
        ],

    ],

    'question_type' => [
        'single_choice' => 'Single choice',
        'multi_choice' => 'Multiple choice',
        'text' => 'Text (single line)',
        'textarea' => 'Text (multi line)',
        'number' => 'Number',
        'email' => 'Email address',
        'phone' => 'Phone number',
        'date' => 'Date',
        'postal_code' => 'Postal code',
        'image_choice' => 'Image choice',
        'slider' => 'Slider',
        'consent' => 'Consent',
        'info' => 'Info text (no input)',
    ],

    'condition' => [
        'errors' => [
            'cycle_detected' => 'The branching rules of this funnel form a loop: step :step was reached again after :max steps.',
        ],
    ],

    'result' => [
        'errors' => [
            'invalid_range' => 'The result range ":title" is invalid: the lower bound :min is above the upper bound :max.',
            'overlap' => 'The result ranges ":first" (:first_range) and ":second" (:second_range) overlap.',
            'gap_single' => 'There is no result for a score of :score.',
            'gap_range' => 'There is no result for scores :from to :to.',
        ],
    ],

    'version' => [
        'errors' => [
            'not_publishable' => 'This funnel cannot be published. :reasons',
            'no_steps' => 'The funnel does not have a single step.',
            'no_contact_field' => 'The funnel is missing a contact field - at least one of: :fields.',
            'not_updatable' => 'A published funnel version cannot be changed.',
            'not_deletable' => 'A published funnel version cannot be deleted.',
        ],
    ],

    // GDPR access and erasure requests (FB-038).
    'gdpr' => [
        'nav_label' => 'Data protection requests',
        'heading' => 'Access and erasure requests',
        'section_heading' => 'Requests by data subjects',
        'section_body' => 'Access requests (Art. 15 GDPR) and erasure requests (Art. 17 GDPR) are handled here. The search runs on the email address the person entered in the funnel.',
        'section_hint' => 'An erasure removes the personal reference, not the record: state, settled price and timestamps remain so that past billing stays consistent.',
        'email' => 'Email address',
        'email_helper' => 'The address the person entered in the funnel. Case does not matter.',
        'export' => [
            'action' => 'Provide access',
            'heading' => 'Download the access report as JSON',
            'description' => 'Contains everything stored for this address: the leads with all their fields, their state log and their answers. The action is recorded in the audit log.',
            'submit' => 'Download',
            'done' => 'Access report created for :count lead(s).',
            'empty' => 'No data is stored for this address.',
        ],
        'erase' => [
            'action' => 'Erase data',
            'heading' => 'Execute erasure request',
            'description' => 'The personal reference of every lead for this address is removed. This cannot be undone. The action is recorded in the audit log.',
            'submit' => 'Remove personal data',
            'done' => 'Personal data removed from :count lead(s).',
            'empty' => 'No data is stored for this address.',
        ],
    ],

    // FB-015: funnel builder
    'builder' => [
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
    ],

];
