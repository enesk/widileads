<?php

/*
|--------------------------------------------------------------------------
| Phone contact (FB-080 ff.)
|--------------------------------------------------------------------------
*/

return [

    'caller_id' => [

        'heading' => 'Your phone number',
        'nav_label' => 'Phone number',
        'description' => 'The person you call sees your own number. It has to be verified once.',

        'phone_number' => 'Phone number',
        'phone_number_helper' => 'Including country code, for example +49 30 1234567. Without a country code a German number is assumed.',

        'current_heading' => 'Verification status',
        'status_label' => 'Status',
        'code_label' => 'Spoken code',
        'code_hint' => 'You will receive a call reading out this code. Answer it and follow the instructions.',

        'submit' => 'Request verification',

        'requested' => 'Verification requested',
        'requested_body' => 'You will be called shortly. The spoken code is shown on this page.',
        'invalid_number' => 'That is not a dialable phone number.',
        'provider_failed' => 'The verification could not be requested. Please try again later.',

        'portal' => [
            'back' => 'Phone numbers',
            'heading' => 'Confirm your phone number',
            'description' => 'We connect you to the enquirer over this number. We call you once to confirm it.',

            'steps' => [
                'enter' => 'Enter number',
                'calling' => 'Take the call',
                'code' => 'Enter code',
            ],

            'label' => 'Name',
            'label_placeholder' => 'e.g. office, mobile',
            'label_hint' => 'For your overview only.',
            'number' => 'Phone number',
            'number_placeholder' => '172 1234567',
            'number_hint' => 'Without the leading zero. Mobile or landline, both work.',
            'country' => 'Country code',
            'explainer' => 'Your phone rings in a moment. A recording reads out a 6-digit code that you enter here. The call is free and takes less than a minute.',
            'cancel' => 'Cancel',
            'start' => 'Call me now',

            'calling_heading' => 'Your phone is about to ring',
            'calling_text' => 'We are calling :number. Pick up and listen to the code.',
            'calling_spinner' => 'Connecting the call …',
            'calling_code' => 'Expected code: :code',
            'have_code' => 'I have the code',
            'other_number' => 'Different number',
            'no_call' => 'No call after 60 seconds? Then you can try again.',

            'code_heading' => 'Enter code',
            'code_text' => 'The 6-digit code from the recording. Press 1 to hear it again.',
            'code_field' => 'Confirmation code',
            'code_digit' => 'Digit :position',
            'code_wrong' => 'That code is wrong. :count attempts left.',
            'code_exhausted' => 'That code is wrong. Request the call again.',
            'code_ttl' => 'The code is valid for :minutes minutes.',
            'call_again' => 'Call me again',
            'confirm' => 'Confirm number',

            'done_heading' => 'Number confirmed',
            'done_text' => ':number is now enabled for calls through :app.',
            'make_default' => 'Use as default number',
            'to_marketplace' => 'To the marketplace',
            'to_numbers' => 'To my phone numbers',

            'why_heading' => 'Why we call',
            'why_one' => 'With "Call now" your phone rings first, then we connect you to the enquirer.',
            'why_two' => 'The enquirer sees our portal number, not yours.',
            'why_three' => 'Only confirmed numbers can start calls, so nobody can call at your expense.',

            'verified_heading' => 'Already confirmed',
            'default_label' => 'Default',
            'trouble_heading' => 'Problems?',
            'trouble_text' => 'No call arriving: check whether the number blocks withheld callers. Our number is :number.',
            'support' => 'Contact support',

            'failed' => 'The confirmation failed. Check the number and try again.',
        ],

        'status' => [
            'pending' => 'Verification in progress',
            'verified' => 'Verified',
            'expired' => 'Expired',
            'failed' => 'Failed',
        ],

    ],

    'attempt' => [

        'action' => 'Call lead',
        'submit' => 'Start call',
        'help' => 'We call you first on your verified number and then connect you to the lead. The lead sees your own verified number, so a call back reaches you directly.',

        'started' => 'Call started',
        'started_body' => 'Your phone will ring shortly. Answer it and you will be connected to the lead.',

        'status' => [
            'queued' => 'Dialing',
            'ringing' => 'Ringing',
            'in_progress' => 'In progress',
            'completed' => 'Completed',
            'no_answer' => 'No answer',
            'busy' => 'Busy',
            'failed' => 'Failed',
            'canceled' => 'Canceled',
        ],

        'outcome' => [
            'answered' => 'Reached',
            'failed_valid' => 'Not reached',
            'failed_ignored' => 'Does not count',
        ],

        'errors' => [
            'caller_id_missing' => 'Verify your own phone number first.',
            'lead_number_missing' => 'This lead has no phone number.',
            'foreign_purchase' => 'This purchase belongs to a different workspace.',
            'not_configured' => 'The phone integration is not set up yet.',
            'lead_resolved' => 'The reachability of this lead has already been decided.',
            'too_soon' => 'The next valid attempt is possible from :time.',
            'already_running' => 'A call to this lead is already in progress.',
            'provider_failed' => 'The call could not be started. Please try again later.',
        ],

    ],

    'panel' => [

        'heading' => 'Call and reachability',
        'action' => 'Call now',
        'calling' => 'We are calling you on :number — please pick up.',

        'history' => 'Attempts',
        'counter' => 'Failed attempts :count of :required',
        'not_counted' => 'not counted',
        'no_attempts' => 'No call has been started for this lead yet.',

        'rule_hint' => 'A lead only counts as unreachable after :attempts failed attempts on :days different days (at least :hours hours apart). Deadline: :deadline.',

        'badge' => [
            'open' => 'Open',
            'billable' => 'Reached — billed',
            'unreachable' => 'Unreachable',
        ],

        'result' => [
            'running' => 'Call in progress',
            'talk' => 'Call :duration min',
            'voicemail' => 'Voicemail',
            'no_answer' => 'Not picked up',
            'busy' => 'Busy',
            'failed' => 'Failed',
            'canceled' => 'Canceled',
        ],

        'ignored' => [
            'too_soon' => 'Too soon after the previous attempt',
            'lead_closed' => 'The lead had already been decided',
            'buyer_no_answer' => 'You did not pick up yourself',
            'twilio_error' => 'The call was never established',
            'other' => 'Does not count',
        ],

        'list' => [
            'contact_status' => 'Reachability',
            'attempts' => 'Failed attempts',
        ],

    ],

    'bridge' => [
        'connecting' => 'You are being connected to the lead.',
        'closed' => 'This lead has already been closed.',
    ],

    'reachability' => [

        'heading' => 'Lead reachability',
        'nav_label' => 'Reachability',
        'description' => 'Share of leads a buyer could not reach by phone. The average across all buyers is :average %; buyers more than :points percentage points above it are highlighted.',
        'hint' => 'The rate refers to decided leads (reached or unreachable). Leads whose deadline is still running are listed under "Open" and are not counted. Highlighted does not mean guilty - the overview only shows where a closer look pays off.',
        'empty' => 'No purchased leads for this period.',

        'from' => 'Decided from',
        'to' => 'Decided until',
        'export' => 'Download as CSV',

        'buyer' => 'Buyer',
        'unknown_buyer' => 'Unknown buyer',
        'leads_total' => 'Leads total',
        'billable' => 'Reached',
        'unreachable' => 'Unreachable',
        'open' => 'Open',
        'rate' => 'Rate',
        'deviation' => 'Deviation (pp)',
        'flagged' => 'Outlier',
        'total' => 'All buyers',

        'csv' => [
            'buyer' => 'Buyer',
            'leads_total' => 'Leads total',
            'billable' => 'Reached',
            'unreachable' => 'Unreachable',
            'open' => 'Open',
            'rate' => 'Rate in percent',
            'deviation' => 'Deviation in percentage points',
            'flagged' => 'Outlier',
            'average' => 'All buyers',
        ],

        'detail' => [
            'heading' => 'Lead #:id',
            'state' => 'Status',
            'contact_status' => 'Reachability',
            'resolved_by' => 'Decided by',
            'delivered_at' => 'Delivered at',
            'deadline_at' => 'Deadline',
            'resolved_at' => 'Decided at',

            'attempts' => 'Call attempts',
            'attempts_hint' => 'All call attempts for this lead, newest first. Read only - nothing can be changed here.',
            'no_attempts' => 'No call has been started for this lead yet.',

            'when' => 'Time',
            'buyer' => 'Buyer',
            'status' => 'Status',
            'dial_status' => 'Lead leg (dial_status)',
            'answered_by' => 'Detection (answered_by)',
            'duration' => 'Duration',
            'seconds' => ':seconds s',
            'outcome' => 'Outcome',
            'ignore_reason' => 'Ignored because',
            'call_sid' => 'CallSid',
            'dial_sid' => 'Dial CallSid',
            'raw_payload' => 'Raw payload from Twilio',
            'no_payload' => 'No raw payload stored.',
        ],

    ],

    'reminder' => [

        'mail' => [
            'subject' => 'Your deadline for a lead ends tomorrow',
            'heading' => 'Deadline ends in less than 24 hours',
            'reference_label' => 'Reference',
            'intro' => 'The deadline to reach this lead by phone ends in less than 24 hours. Until then you can call the lead from the portal.',
            'deadline_at' => 'Deadline ends on :date at :time.',
            'attempts_label' => 'Documented attempts so far',
            'attempts_value' => ':attempts of :required',
            'warning' => 'Without :required documented attempts the lead counts as reachable and will be billed.',
            'cta' => 'Open lead in the portal',
        ],

    ],

    'contact_status' => [
        'open' => 'Open',
        'billable' => 'Reached',
        'unreachable' => 'Unreachable',
    ],

    'phone_release' => [
        'pending' => 'The full phone number is released once the lead is billable. Until then, reach the lead with "Call".',
        'unreachable' => 'This lead counts as unreachable. The phone number will not be released.',
    ],

    'resolution' => [
        'answered' => 'Call answered',
        'three_attempts' => 'Attempts exhausted',
        'deadline' => 'Deadline passed',
    ],

];
