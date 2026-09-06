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

];
