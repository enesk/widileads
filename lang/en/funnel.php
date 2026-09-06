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

];
