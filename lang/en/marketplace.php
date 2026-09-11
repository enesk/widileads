<?php

/*
|--------------------------------------------------------------------------
| Lead marketplace (FB-E5)
|--------------------------------------------------------------------------
|
| A translation file per topic instead of another block in funnel.php: two
| tickets appending to the same collection file collide systematically -- one
| file per responsibility never does.
|
| Referenced as __('marketplace.<key>'). The marketplace tickets FB-051 to
| FB-060 add their own sections next to 'buyer'.
|
*/

return [

    'buyer' => [

        'status' => [
            'pending' => 'Under review',
            'active' => 'Approved',
            'rejected' => 'Rejected',
        ],

        'not_approved' => 'This workspace has not been approved for the marketplace yet.',

        'form' => [
            'heading' => 'Register as a buyer',
            'description' => 'After you submit, we review your details and unlock access to the lead marketplace.',
            'company_name' => 'Company',
            'contact_name' => 'Contact person',
            'contact_email' => 'Contact email address',
            'contact_phone' => 'Phone (optional)',
            'broker_register_number' => 'Insurance intermediary register no. (optional)',
            'broker_register_number_helper' => 'Registration number under section 34d GewO, if you have one. Buyers without an intermediary licence leave this empty.',
            'vat_id' => 'VAT identification number',
            'av_accepted' => 'I confirm the data processing agreement.',
            'av_accepted_helper' => 'Without this confirmation we may not pass any personal lead data on to you.',
            'submit' => 'Submit registration',
            'already_registered' => 'A registration already exists for this account.',
            'received_heading' => 'Registration received',
            'received_text' => 'We have received the registration for ":company".',
            'received_hint' => 'Once your access is approved, you can reach the lead marketplace. Until then no leads are visible.',
        ],

        'validation' => [
            'required' => 'Please fill in the ":attribute" field.',
            'email' => 'Please provide a valid email address for ":attribute".',
            'max' => 'The ":attribute" field is too long.',
            'vat_id' => 'The VAT identification number starts with the country code, e.g. DE123456789.',
            'av_accepted' => 'Without confirming the data processing agreement we cannot set up your access.',
        ],

        'resource' => [
            'label' => 'Buyer registration',
            'plural_label' => 'Buyer registrations',
            'empty_heading' => 'No registrations yet',
            'empty_description' => 'Registrations appear here as soon as a buyer signs up.',
            'read_only' => 'These details come from the buyer and are not edited. Approving and rejecting are the available actions.',
        ],

        'fields' => [
            'company_name' => 'Company',
            'status' => 'Status',
            'contact_name' => 'Contact person',
            'contact_email' => 'Email',
            'contact_phone' => 'Phone',
            'vat_id' => 'VAT ID',
            'broker_register_number' => 'Intermediary register no.',
            'av_accepted_at' => 'Data processing agreement confirmed at',
            'tenant' => 'Workspace',
            'created_at' => 'Received at',
            'reviewed_by' => 'Decided by',
            'reviewed_at' => 'Decided at',
            'rejection_reason' => 'Reason for rejection',
        ],

        'hints' => [
            'broker_register_number' => 'Optional: not every buyer is an insurance intermediary. Review case by case when in doubt.',
            'rejection_reason' => 'At least 10 characters. The reason is shown to the buyer.',
        ],

        'actions' => [
            'approve' => 'Approve',
            'approve_confirm' => 'This grants the buyer access to the lead marketplace.',
            'approved' => 'Buyer has been approved.',
            'reject' => 'Reject',
            'rejected' => 'Buyer has been rejected.',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Purchase criteria (FB-051)
    |--------------------------------------------------------------------------
    */

    'profile' => [

        'heading' => 'Purchase criteria',
        'nav_label' => 'Purchase criteria',
        'description' => 'Define which leads you want to see. An empty field does not restrict anything -- without a setting you see everything.',
        'saved' => 'Purchase criteria saved.',
        'submit' => 'Save',

        'funnels' => 'Questionnaires',
        'funnels_helper' => 'Without a selection you see leads from every questionnaire.',
        'no_funnels' => 'No questionnaire is published at the moment.',

        'postal_prefixes' => 'Regions',
        'postal_prefixes_helper' => 'Beginnings of postal codes, separated by commas. "76" covers everything from 76001 to 76999.',

        'answer_filters' => 'Answer filters',
        'answer_filters_helper' => 'One field key per row plus the answers you accept. For a multiple choice question one match is enough.',
        'field_key_placeholder' => 'e.g. tierart',
        'values_placeholder' => 'e.g. hund, katze',
        'add_filter' => 'Add filter',
        'remove_filter' => 'Remove',
        'no_answer_filters' => 'No answer filter yet -- you see leads with any answers.',

        'min_score' => 'Minimum score',
        'min_score_helper' => 'Empty: no lower bound. Leads without a score drop out as soon as a value is set here.',

        'daily_limit' => 'Daily limit',
        'daily_limit_helper' => 'Maximum number of automatic purchases per day. Empty or 0: no limit.',

        'auto_buy' => 'Buy matching leads automatically',
        'auto_buy_helper' => 'With automatic buying off you see matching leads in the marketplace and decide yourself.',

        'notify_email' => 'Send notifications to',
        'notify_email_helper' => 'Without a value we use the address from your registration.',

        'validation' => [
            'too_many_prefixes' => 'At most :max regions are allowed.',
            'prefix_format' => '":prefix" is not a region: digits only, at most :max characters.',
            'notify_email' => 'Please provide a valid email address.',
            'min_score' => 'The minimum score must be a whole number.',
            'daily_limit' => 'The daily limit must be a whole number of 0 or more.',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Marketplace (FB-053)
    |--------------------------------------------------------------------------
    */

    'listing' => [

        'result_count' => '{0} No lead matches your criteria|{1} 1 lead matches your criteria|[2,*] :count leads match your criteria',
        'empty_hint' => 'New enquiries appear here automatically. Widen your criteria to see more leads.',
        'no_funds' => 'Your balance is used up. Top up to buy leads.',
        'price_exceeds_balance' => 'Your available balance is not enough for this lead.',
        'badge_new' => 'New',
        'locked_contact' => 'Phone and email after the purchase',
        'price' => 'Price: :amount',
        'yesterday' => 'yesterday, :time',
        'gone' => 'Someone else just bought this lead.',
        'show_less' => 'Show less',
        'show_more' => '{1} +1 more detail|[2,*] +:count more details',

        'sort' => [
            'label' => 'Sorting',
            'newest' => 'Newest first',
            'oldest' => 'Oldest first',
            'score' => 'Highest score first',
        ],

        'heading' => 'Marketplace',
        'nav_label' => 'Marketplace',
        'description' => 'Available leads matching your purchase criteria. Contact details become visible after the purchase.',
        'no_profile' => 'You have not set any purchase criteria yet -- you are therefore seeing every available lead.',
        'empty' => 'No available lead currently matches your purchase criteria.',

        'only_watchlisted' => 'Watchlist only',

        'score' => ':score points',
        'region' => 'Region',
        'email' => 'Email',
        'phone' => 'Phone',
        'result' => 'Result',
        'unknown_funnel' => 'Unknown questionnaire',
        'taken' => 'Taken',
        'masked_hint' => 'Contact details stay hidden until the purchase.',

        'watch' => 'Add to watchlist',
        'unwatch' => 'Remove from watchlist',

        'purchase' => 'Buy lead',
        'purchase_unavailable' => 'Buying is not enabled yet.',

    ],

    /*
    |--------------------------------------------------------------------------
    | Purchase (FB-054)
    |--------------------------------------------------------------------------
    */

    'purchase' => [

        'balance' => 'Available balance: :amount',
        'confirm' => 'Buy this lead now? The price is reserved from your balance and the contact details are released.',
        'done' => 'Lead purchased. The contact details are visible now and the confirmation is on its way.',

        'errors' => [
            'already_taken' => 'This lead has been taken in the meantime. Another buyer was faster.',
            'buyer_not_approved' => 'This workspace has not been approved for the marketplace yet.',
            'already_bought' => 'You have already bought this lead.',
            'own_lead' => 'This lead comes from one of your own questionnaires and cannot be bought.',
            'price_changed' => 'The price has changed, please check the lead again.',
        ],

        'mail' => [
            'subject' => 'Your purchased lead',
            'label' => 'Lead purchase',
            'funnel_label' => 'Questionnaire',
            'heading' => 'Lead purchased',
            'intro' => 'You purchased a lead from the questionnaire ":funnel". The contact details are below.',
            'contact_heading' => 'Contact details',
            'phone_note' => 'For data protection reasons the phone number is not part of this email. Use the "Call" button in the portal to reach the lead; the number is released once the lead is billable.',
            'cta' => 'Open lead in the portal',
            'outro' => 'Get in touch soon -- the conversion rate drops with every day that passes.',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Sale mode (FB-055)
    |--------------------------------------------------------------------------
    */

    'sale_mode' => [
        'exclusive' => 'Exclusive',
        'shared' => 'Shared',
        'buyers' => ':buyers of :max buyers',
    ],

    /*
    |--------------------------------------------------------------------------
    | My leads (FB-057)
    |--------------------------------------------------------------------------
    */

    'purchased' => [

        'result_count' => '{0} No purchased leads|{1} 1 purchased lead|[2,*] :count purchased leads',

        'sort' => [
            'newest' => 'Most recently purchased first',
            'oldest' => 'Earliest purchase first',
        ],
        'detail' => [
            'subheading' => 'From :funnel, purchased on :date',
            'facts' => 'Purchase',
            'back' => 'Back to my leads',
            'open' => 'Open',
            'no_answers' => 'No further details were submitted with this lead.',
            'price' => 'Purchase price',

            'price_status' => [
                'reserved' => 'Reserved. It is only charged once reachability is confirmed.',
                'captured' => 'Charged.',
                'released' => 'Released. Nothing was charged.',
                'refunded' => 'Refunded.',
            ],
            'feedback_saved' => 'Feedback saved.',
        ],

        'heading' => 'My leads',
        'nav_label' => 'My leads',
        'description' => 'The leads you bought. Contact details are shown in full.',
        'empty' => 'You have not bought a lead yet.',
        'only_without_feedback' => 'Without feedback only',
        'export' => 'Download as CSV',
        'bought_at' => 'Bought on :date',
        'feedback_given' => 'Your feedback: :feedback',

        'feedback' => [
            'interested' => 'Useful',
            'not_interested' => 'Not useful',
        ],

        'csv' => [
            'purchased_at' => 'Bought on',
            'funnel' => 'Questionnaire',
            'name' => 'Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'postal_code' => 'Postal code',
            'score' => 'Score',
            'feedback' => 'Feedback',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Complaint (FB-058)
    |--------------------------------------------------------------------------
    */

    'complaint' => [

        'refund_reason' => 'Approved complaint.',

        'mail' => [
            'subject' => 'Complaint about lead #:lead',
            'label' => 'Complaint',
            'heading' => 'A buyer has filed a complaint',
            'intro' => 'A buyer is asking for this lead to be moved to a different state. If the complaint is approved, the price is refunded to their balance.',
            'filed_at' => 'Filed on :date at :time',
            'facts_heading' => 'The request',
            'lead' => 'Lead',
            'buyer' => 'Buyer',
            'cta' => 'Review complaint',
            'outro' => 'A person decides on this request. Until then the lead stays as it is.',
        ],

        'open' => 'Report this lead',
        'help' => 'Request that this lead counts as unreachable or invalid. A member of staff reviews the request; if it is accepted, the price is refunded to your balance.',
        'reason_placeholder' => 'What happened? For example: called three times on different days, nobody reachable.',
        'submit' => 'Submit request',
        'filed' => 'Complaint submitted (:state) -- status: :status.',

        'status' => [
            'pending' => 'Under review',
            'approved' => 'Accepted',
            'rejected' => 'Rejected',
        ],

        'errors' => [
            'not_your_purchase' => 'This purchase does not belong to your workspace.',
            'unsupported_state' => 'Only "unreachable" or "invalid" can be requested.',
            'reason_required' => 'Please describe what happened -- without a reason the request cannot be reviewed.',
            'lead_already_settled' => 'This lead is already settled and can no longer be reported.',
            'deadline_elapsed' => 'The complaint period for this lead has elapsed.',
            'already_filed' => 'A complaint for this purchase already exists.',
            'already_decided' => 'This complaint has already been decided.',
        ],

        'resource' => [
            'label' => 'Complaint',
            'plural_label' => 'Complaints',
            'empty_heading' => 'No complaints',
            'empty_description' => 'Requests appear here as soon as a buyer reports a lead.',
            'read_only' => 'The request comes from the buyer and is not edited. Accepting and rejecting are the available actions.',
        ],

        'fields' => [
            'created_at' => 'Received at',
            'status' => 'Status',
            'buyer' => 'Buyer',
            'requested_state' => 'Requested',
            'reason' => 'Reason',
            'reviewed_by' => 'Decided by',
            'reviewed_at' => 'Decided at',
            'decision_note' => 'Decision note',
        ],

        'hints' => [
            'decision_note' => 'At least 10 characters. The note explains the rejection.',
        ],

        'actions' => [
            'approve' => 'Accept',
            'approve_confirm' => 'The lead moves to the requested state and the price is refunded to the buyer.',
            'approved' => 'Complaint accepted, price refunded.',
            'reject' => 'Reject',
            'rejected' => 'Complaint rejected.',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Billing (FB-059)
    |--------------------------------------------------------------------------
    */

    'billing' => [

        'heading' => 'Billing',
        'nav_label' => 'Billing',
        'description' => 'What a buyer received in a month and what became of it. Payment is prepaid from the wallet -- nothing is charged here, this is an account of what happened.',
        'buyer' => 'Buyer',
        'month' => 'Month',
        'no_buyer' => 'No buyer has been created yet.',
        'export' => 'Download as CSV',

        'leads' => 'Leads by state',
        'money' => 'Wallet and revenue',
        'purchases' => 'Purchases in total',
        'revenue' => 'Revenue',
        'topped_up' => 'Wallet topped up',
        'captured' => 'Captured for leads',
        'refunded' => 'Refunded',
        'captured_expected' => 'Captured purchases per receipts',
        'money_hint' => 'The captured amount and the sum of the purchase prices settled in this period have to match. If they differ, wallet and purchase flow have drifted apart.',

        'invoices' => 'Invoices',
        'invoices_hint' => 'The invoices for this month\'s wallet top-ups. SaaSykit issued them at checkout.',
        'no_invoices' => 'The wallet was not topped up in this month.',

        'csv' => [
            'buyer' => 'Buyer',
            'month' => 'Month',
            'state' => 'State',
            'count' => 'Count',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Buyer overview (FB-060)
    |--------------------------------------------------------------------------
    */

    'overview' => [

        'heading' => 'Buyers',
        'nav_label' => 'Buyers',
        'description' => 'Revenue and complaint rate per buyer. The average is :average percent; anyone more than :points percentage points above it is flagged.',
        'empty' => 'No buyer has been created yet.',

        'buyer' => 'Buyer',
        'purchases' => 'Purchases',
        'revenue' => 'Revenue',
        'complaints' => 'Accepted complaints',
        'rate' => 'Rate',
        'deviation' => 'Deviation in points',
        'flagged' => 'Flagged',

        'hint' => 'Flagged does not mean at fault: a high rate can mean a buyer received poor leads -- or that they complain too readily. The overview only says where it is worth looking.',

    ],

    /*
    |--------------------------------------------------------------------------
    | Wallet and ledger (LP-WALLET)
    |--------------------------------------------------------------------------
    */

    'wallet' => [

        'buyer' => [

            'title' => 'Balance & transactions',
            'nav_label' => 'Balance',
            'top_up_heading' => 'Top up balance',

            'balance' => [
                'available_label' => 'Available',
                'reserved_hint' => 'of which reserved: :amount',
                'reserved_tooltip' => 'Reserved amounts are only charged once reachability is confirmed.',
            ],

            'history' => [
                'heading' => 'Transaction history',
                'date' => 'Date',
                'description' => 'Description',
                'amount' => 'Amount',
                'balance_after' => 'Balance after',
                'empty' => 'There are no entries for this period.',
                'lead_link' => 'To lead #:lead',
                'reserved_note' => 'A reservation does not change the balance: it blocks the amount until reachability is settled. Only the charge reduces the balance.',
                'result_count' => '{0} No entries|{1} 1 entry|[2,*] :count entries',

                'filter' => [
                    'type' => 'Type',
                    'period' => 'Period',
                    'all_types' => 'All types',
                    'all_time' => 'All time',
                    'days' => 'Last :days days',
                ],
            ],

        ],

        /*
        |----------------------------------------------------------------------
        | Admin tools for the wallet (LP-WALLET-013)
        |----------------------------------------------------------------------
        */

        'admin' => [

            'resource' => [
                'label' => 'Wallet',
                'plural_label' => 'Wallets',
                'read_only' => 'Balances cannot be edited here. Every change needs a ledger entry as its record – use the manual adjustment for that.',
            ],

            'platform_owner' => 'Platform',

            'owner_type' => [
                'buyer' => 'Buyer',
                'seller' => 'Seller',
                'platform' => 'Platform',
            ],

            'type' => [
                'topup' => 'Top-up',
                'reserve' => 'Reservation',
                'capture' => 'Capture',
                'release' => 'Release',
                'refund' => 'Refund',
                'earning' => 'Earning',
                'commission' => 'Commission',
                'payout' => 'Payout',
                'adjustment' => 'Adjustment',
                'opening_balance' => 'Opening balance',
            ],

            'fields' => [
                'id' => 'No.',
                'owner' => 'Owner',
                'owner_type' => 'Type',
                'balance' => 'Balance',
                'reserved' => 'Reserved',
                'available' => 'Available',
                'currency' => 'Currency',
                'sum' => 'Total',
                'created_at' => 'Time',
                'type' => 'Kind',
                'amount' => 'Amount',
                'description' => 'Description',
                'balance_after' => 'Balance after',
                'reserved_after' => 'Reserved after',
                'idempotency_key' => 'Idempotency key',
                'reference' => 'Reference',
                'created_by' => 'Posted by',
                'meta' => 'Details',
                'amount_cents' => 'Amount in cents',
                'reason' => 'Reason',
                'allow_negative' => 'Allow negative balance',
            ],

            'hints' => [
                'amount_cents' => 'Positive credits, negative debits. 1500 is 15.00 €.',
                'reason' => 'Stays in the ledger permanently and is the only record of why this entry exists.',
                'allow_negative' => 'Only enable if the balance is meant to go negative. Otherwise an excessive debit fails – usually a typo.',
            ],

            'actions' => [
                'adjust' => 'Manual adjustment',
                'adjust_description' => 'Posts a manual adjustment to the ledger. Existing entries stay untouched; corrections are made by counter-entry.',
                'adjust_submit' => 'Post adjustment',
                'adjusted' => 'Adjustment posted',
            ],

            'descriptions' => [
                'adjustment' => 'Manual adjustment: :reason',
            ],

            'ledger' => [
                'heading' => 'Ledger',
                'description' => 'Every entry of this wallet, newest first. Expanding a row shows reference, idempotency key and details.',
                'empty_heading' => 'No entries yet',
            ],

            'stats' => [
                'platform_balance' => 'Platform wallet',
                'platform_balance_hint' => 'Accumulated commission less refunds and adjustments',
                'commission_total' => 'Commission total',
                'commission_total_hint' => 'Sum of all commission entries, reversals included',
                'open_payouts' => 'Open payouts',
                'open_payouts_hint' => '{0} No open request|{1} :count request awaiting transfer|[2,*] :count requests awaiting transfer',
            ],

            'payout' => [

                'resource' => [
                    'label' => 'Payout',
                    'plural_label' => 'Payouts',
                ],

                'read_only' => 'The amount left the seller wallet when the request was made. Rejecting it books the amount back.',
                'empty_heading' => 'No open payout',
                'empty_description' => 'As soon as a seller requests a payout, it shows up here.',
                'iban_changed' => 'Careful: the stored IBAN changed after the request, which was made against •••• :last4.',

                'fields' => [
                    'requested_at' => 'Requested',
                    'seller' => 'Seller',
                    'amount' => 'Amount',
                    'iban' => 'IBAN',
                    'status' => 'Status',
                    'processed_at' => 'Decided',
                    'processed_by' => 'Decided by',
                    'note' => 'Note',
                ],

                'hints' => [
                    'note_optional' => 'Optional, for instance the transfer reference.',
                    'note_required' => 'The seller receives the rejection by email; the reason belongs with it.',
                ],

                'actions' => [
                    'mark_paid' => 'Transferred',
                    'mark_paid_confirm' => 'Only confirm once the transfer has actually been made. Nothing is posted – the money left the wallet with the request.',
                    'marked_paid' => 'Marked as transferred',
                    'reject' => 'Reject',
                    'reject_confirm' => 'The amount is booked back to the seller wallet as an adjustment.',
                    'rejected' => 'Payout rejected',
                ],

            ],

            'purchase' => [

                'resource' => [
                    'label' => 'Lead purchase',
                    'plural_label' => 'Lead purchases',
                ],

                'read_only' => 'Purchase records are not edited. The money side only changes through a refund.',
                'empty_heading' => 'No lead purchase yet',

                'status' => [
                    'reserved' => 'Reserved',
                    'captured' => 'Captured',
                    'released' => 'Released',
                    'refunded' => 'Refunded',
                ],

                'fields' => [
                    'purchased_at' => 'Purchased',
                    'lead' => 'Lead',
                    'buyer' => 'Buyer',
                    'seller' => 'Seller',
                    'status' => 'Status',
                    'price' => 'Price',
                    'commission' => 'Commission',
                    'seller_net' => 'Seller earning',
                    'captured_at' => 'Captured',
                    'released_at' => 'Released',
                    'refunded_at' => 'Refunded',
                    'reason' => 'Reason',
                ],

                'hints' => [
                    'reason' => 'Stays permanently in every ledger entry of this refund.',
                ],

                'actions' => [
                    'refund' => 'Refund lead',
                    'refund_confirm' => 'The price goes back to the buyer, earning and commission are reversed – even if that pushes the seller wallet negative. If there is an open complaint for this purchase, decide it there instead.',
                    'refunded' => 'Lead purchase refunded',
                ],

            ],

        ],

        'top_up' => [
            'title' => 'Top up balance',
            'nav_label' => 'Balance',
            'balance_label' => 'Your balance',
            'reserved_label' => 'Of which reserved',
            'available_label' => 'Available',
            'description' => 'Your balance is used to buy leads on the marketplace. Pick an amount, payment runs through the usual checkout. The amount is credited as soon as the payment is confirmed.',
            'amount_label' => 'Amount',
            'amount_hint' => 'At least :min €, at most :max €. Whole euros only.',
            'submit' => 'Continue to payment',
            'checkout_hint' => 'Your balance lets you buy leads in the marketplace. It does not expire.',
            'payment_hint' => 'Payment is handled by the portal payment provider. You will find the invoice and receipt under "Orders".',

            'errors' => [
                'min' => 'The smallest top-up amount is :amount €.',
                'max' => 'The largest top-up amount is :amount €.',
                'unavailable' => 'Topping up is not possible at the moment. Please contact support.',
            ],

            'success' => [
                'heading' => 'Balance topped up',
                'pending_heading' => 'Almost there',
                'text' => ':amount has been added to your balance.',
                'pending_text' => 'As soon as the payment is confirmed we will credit the amount. For bank transfers this usually takes one to two working days.',
                'balance_label' => 'Your balance',
                'pending_addition' => '+:amount once the payment arrives',
                'unknown_heading' => 'We do not know this order',
                'unknown_text' => 'The link may have expired. You will find your balance on the marketplace.',
            ],

            'mail' => [
                'subject' => 'Balance topped up: :amount',
                'label' => 'Balance',
                'heading' => 'Balance topped up',
                'intro' => 'Your payment has arrived. We have added :amount to your balance.',
                'amount_label' => 'Topped up',
                'balance_label' => 'New balance',
                'history_hint' => 'You can review every entry of your balance in the transaction history in the portal at any time.',
                'receipt_hint' => 'The invoice and payment receipt for this top-up are in the order confirmation we sent you as well.',
                'cta' => 'To the transaction history',
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | Order confirmation as payment receipt (LP-WALLET-019)
        |----------------------------------------------------------------------
        |
        | This mail is the receipt for the payment: order number, line items,
        | discount and total. The credit itself is confirmed by `top_up.mail`
        | with amount and new balance -- the receipt therefore points at it
        | instead of repeating the balance.
        */

        'order_mail' => [
            'subject' => 'Your order at :app',
            'label' => 'Payment receipt',
            'heading' => 'Order confirmed',
            'intro' => 'Thank you for your order. The payment arrived.',
            'intro_topup' => 'Thank you for your top-up. The payment arrived and the amount was credited to your wallet.',
            'order_number' => 'Order number',
            'date' => 'Ordered on',
            'items_heading' => 'Line items',
            'quantity' => 'Quantity: :count',
            'topup_amount' => 'Credited to your wallet: :amount',
            'subtotal' => 'Subtotal',
            'discount' => 'Discount',
            'total' => 'Total',
            'cta' => 'To the marketplace',
            'topup_hint' => 'Your new wallet balance is stated in the top-up confirmation we just sent you.',
            'support' => 'Questions about your order? Write to us at :email.',
            'outro' => 'Kind regards, your :app team',
        ],

        /*
        |----------------------------------------------------------------------
        | Ledger entry types (LP-WALLET-012)
        |----------------------------------------------------------------------
        */

        'types' => [
            'topup' => 'Top-up',
            'reserve' => 'Reserved',
            'capture' => 'Charged',
            'release' => 'Released',
            'refund' => 'Refund',
            'earning' => 'Earning',
            'commission' => 'Commission',
            'payout' => 'Payout',
            'adjustment' => 'Adjustment',
            'opening_balance' => 'Opening balance',
        ],

        /*
        |----------------------------------------------------------------------
        | Seller portal: price, earnings, payout (LP-WALLET-012)
        |----------------------------------------------------------------------
        */

        'seller' => [

            'title' => 'Earnings',
            'nav_label' => 'Earnings',

            'price' => [
                'heading' => 'Price per lead',
                'description' => 'This is the price your leads are offered at in the marketplace.',
                'label' => 'Price per lead (gross, paid by the buyer)',
                'preview' => 'You receive :net per lead (commission :percent %).',
                'change_hint' => 'Price changes apply to new purchases. Leads already sold keep their price.',
                'range' => 'Allowed range: :min to :max.',
                'out_of_range' => 'The price must be between :min and :max.',
                'saved' => 'Price saved.',
                'submit' => 'Save price',
            ],

            'balance' => [
                'available' => 'Payout balance',
                'available_hint' => 'Settled earnings you can have paid out.',
                'pending' => 'Pending settlement',
                'pending_badge' => 'pending',
                'pending_hint' => 'Your share of leads that have not been settled yet. Not a balance: if a purchase is released, the amount is gone.',
                'earnings' => 'Earnings, last :days days',
                'earnings_hint' => 'Sum of credited earnings, less reversed leads.',
            ],

            'history' => [
                'heading' => 'Transaction history',
                'empty' => 'No entries yet.',
                'date' => 'Date',
                'type' => 'Type',
                'description' => 'Description',
                'amount' => 'Amount',
                'balance_after' => 'Balance after',
                'show_more' => 'Show more',
            ],

            'payout' => [
                'heading' => 'Request a payout',
                'description' => 'From :minimum you can have your balance paid out. The amount is debited immediately and transferred within the next business days.',
                'iban_label' => 'IBAN',
                'iban_missing' => 'None on file',
                'iban_cta' => 'Add your IBAN in the workspace settings',
                'amount_label' => 'Amount',
                'amount_hint' => 'Available: :available, minimum :minimum.',
                'below_minimum' => 'A payout is possible from :minimum. Currently available: :available.',
                'confirm' => 'Request the payout now? The amount is debited from your balance immediately.',
                'submit' => 'Request payout',
                'invalid_amount' => 'Please enter a valid amount.',
                'requested' => 'Payout of :amount requested.',
                'history_heading' => 'Previous payouts',
                'history_empty' => 'No payout requested yet.',
                'requested_at' => 'Requested',
                'amount' => 'Amount',
                'status' => 'Status',
                'note' => 'Note',
            ],

        ],

        'errors' => [
            'not_updatable' => 'A wallet entry cannot be changed once it has been created.',
            'not_deletable' => 'A wallet entry cannot be deleted.',
            'insufficient_reserve' => 'Your available balance is not sufficient. Missing: :missing.',
            'insufficient_balance' => 'The balance is not sufficient for this entry: :balance available, :required required.',
            'release_exceeds_reserved' => 'Only :reserved is reserved, but :requested should be released.',
        ],

        'descriptions' => [
            'topup' => 'Top-up via order :order',
            'reserve' => 'Reservation for lead #:lead',
            'release' => 'Reservation for lead #:lead released',
            'release_for_capture' => 'Reservation for lead #:lead released for capture',
            'capture' => 'Charge for lead #:lead',
            'earning' => 'Earning from lead #:lead',
            'commission' => 'Commission from lead #:lead',
            'refund' => 'Refund for lead #:lead',
            'earning_reversal' => 'Reversal of the earning from lead #:lead',
            'commission_reversal' => 'Reversal of the commission from lead #:lead',
            'chargeback' => 'Reversal of the top-up from order :order',
            'payout' => 'Payout #:payout',
            'payout_rejected' => 'Reversal of the rejected payout #:payout',
        ],

        /*
        |----------------------------------------------------------------------
        | Seller payouts (LP-WALLET-010)
        |----------------------------------------------------------------------
        */

        'payout' => [

            'status' => [
                'requested' => 'Requested',
                'paid' => 'Paid out',
                'rejected' => 'Rejected',
            ],

            'profile' => [
                'heading' => 'Bank account',
                'description' => 'We pay out your earnings from sold leads to this account.',
                'iban' => 'IBAN',
                'iban_helper' => 'No IBAN on file yet. We cannot pay out without one.',
                'iban_stored' => 'On file: •••• :last4. Enter a new IBAN to change it, otherwise leave empty.',
                'invalid_iban' => 'That is not a valid IBAN.',
            ],

            'errors' => [
                'missing_iban' => 'We need your IBAN before we can pay out. Please add it to your profile.',
                'below_minimum' => 'Payouts start at :minimum; you requested :amount.',
                'exceeds_balance' => 'That much is not available: :available can be paid out, you requested :amount.',
            ],

            'mail' => [

                'amount_label' => 'Amount',
                'iban_label' => 'IBAN',
                'seller_label' => 'Seller',
                'reference_label' => 'Reference',
                'note_label' => 'Reason',

                'requested' => [
                    'subject' => 'Payout requested: :amount by :seller',
                    'label' => 'Payout',
                    'heading' => 'New payout request',
                    'intro' => ':seller requested a payout of :amount. The amount has already been debited from their balance.',
                    'hint' => 'Transfer the amount and then mark the request as paid in the admin area. Rejecting it credits the balance back automatically.',
                ],

                'processed' => [

                    'label' => 'Payout',

                    'paid' => [
                        'subject' => 'Your payout of :amount is on its way',
                        'heading' => 'Payout transferred',
                        'intro' => 'We have transferred your payout of :amount.',
                        'hint' => 'Depending on your bank the credit takes one to two business days.',
                    ],

                    'rejected' => [
                        'subject' => 'Your payout of :amount was rejected',
                        'heading' => 'Payout rejected',
                        'intro' => 'We could not process your payout of :amount. The amount is back in your balance.',
                        'hint' => 'You can request the payout again at any time once the cause is resolved.',
                    ],

                ],

            ],

        ],

        'opening_balance' => [
            'description' => 'Opening balance carried over from the previous credit system (:credits credits).',
        ],

        'settlement' => [

            'mail' => [
                'subject' => 'Lead purchase not settled: lead #:lead',
                'label' => 'Lead settlement',
                'heading' => 'A resolved lead could not be settled',
                'intro' => 'The contact status of lead #:lead is ":status", but the money side of the purchase remained open after several attempts.',
                'reason' => 'Error',
                'outro' => 'Please check the purchase record: a reservation nobody releases keeps blocking the buyer\'s balance.',
            ],

        ],

        'verify' => [

            'mail' => [
                'subject' => 'Wallet check: :count discrepancy/discrepancies against the ledger',
                'label' => 'Wallet check',
                'heading' => 'Balances differ from the ledger',
                'intro' => 'The daily consistency check found wallets whose cached balance does not match the sum of their entries.',
                'checked' => ':count wallet(s) checked.',
                'wallet' => 'Wallet #:id',
                'balance' => 'Balance',
                'reserved' => 'Reserved',
                'expected' => 'Expected',
                'actual' => 'Actual',
                'repaired' => 'The cached balance was reset to the ledger during this run.',
                'reservation_totals' => 'The reserved amounts of the buyer wallets do not match the open lead purchases: expected :expected, actual :actual.',
                'outro' => 'The ledger is always authoritative. Please find the cause before correcting the balance.',
            ],

        ],

    ],

];
