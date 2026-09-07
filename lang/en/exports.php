<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Lead exports (FB-073)
    |--------------------------------------------------------------------------
    */

    'heading' => 'Exports',
    'nav_label' => 'Export',
    'new' => 'Request a new export',
    'hint' => 'The export runs in the background. Once the file is ready, a time-limited download link appears here.',
    'columns_legend' => 'Columns',
    'request' => 'Request export',
    'requested_at' => 'Requested',
    'requested_by' => 'By',
    'status_label' => 'Status',
    'rows' => 'Rows',
    'download' => 'Download',
    'empty' => 'No export has been requested yet.',

    'status' => [
        'pending' => 'Pending',
        'running' => 'Running',
        'ready' => 'Ready',
        'failed' => 'Failed',
    ],

    'columns' => [
        'id' => 'ID',
        'created_at' => 'Received',
        'funnel' => 'Funnel',
        'lead_state' => 'State',
        'score' => 'Score',
        'result_key' => 'Result',
        'price_at_creation' => 'Price',
        'name' => 'Name',
        'email' => 'Email',
        'phone' => 'Phone',
        'postal_code' => 'Postal code',
        'utm_source' => 'Source',
        'utm_campaign' => 'Campaign',
        'embed_origin' => 'Embedded on',
        'answers' => 'Answers',
    ],

    'errors' => [
        'no_columns' => 'Select at least one column.',
        'no_requester' => 'The export cannot be created without a requesting user.',
        'not_writable' => 'The export file could not be created.',
        'failed' => 'The export was aborted. Please try again.',
    ],

];
