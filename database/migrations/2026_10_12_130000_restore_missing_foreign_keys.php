<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LP-WALLET-014: Fehlende Fremdschluessel nachziehen.
 *
 * Der Live-Stand traegt alle 152 Migrationen als gelaufen, hat aber nur 24 der
 * 153 Fremdschluessel, die ein frischer Migrationslauf anlegt (Ticket #83,
 * Befund aus #80). Betroffen ist nicht nur `lead_purchases.buyer_tenant_id`,
 * sondern fast das ganze Schema: `leads` hat live keinen einzigen
 * Fremdschluessel, `orders`, `subscriptions`, `transactions`, `funnels` und die
 * Pivot-Tabellen der Rollen ebenso wenig. `wallets`, `wallet_transactions`,
 * `payment_methods` und `settlements` sind vollstaendig -- die Geldseite ist
 * also abgesichert, die Lead- und Abo-Seite nicht.
 *
 * Die Liste unten ist nicht von Hand geschrieben, sondern aus einer frischen
 * Pruefdatenbank gezogen (`DB_DATABASE=widileads_fkref php artisan migrate`,
 * dann `information_schema.KEY_COLUMN_USAGE`). Sie ist damit genau das, was die
 * Migrationen zusammen behaupten -- inklusive der vier verkuerzten
 * Constraint-Namen, die einzelne Migrationen ausdruecklich vorgeben
 * (`one_time_product_payment_264vc5` und Geschwister).
 *
 * Der Lauf ist idempotent und greift nie destruktiv ein:
 *
 * 1. Angelegt wird nur, was fehlt. Vorhandene Fremdschluessel bleiben
 *    unberuehrt, auch wenn ihre Regel abweicht -- eine Regel zu aendern ist
 *    Sache der Migration, die sie festlegt (siehe
 *    2026_10_12_120000_restrict_lead_deletion_with_purchases fuer `lead_id`).
 * 2. Fehlt eine Tabelle oder eine Spalte, wird der Eintrag gemeldet und
 *    uebersprungen. Das Schema zu erfinden ist nicht Aufgabe dieses Laufs.
 * 3. Verwaiste Zeilen werden gemeldet, nicht geloescht. Der betroffene
 *    Fremdschluessel bleibt offen, der Lauf geht weiter -- ein Abbruch wuerde
 *    live wegen Altdaten jede folgende Migration aufhalten. Der Scan laeuft
 *    dabei vollstaendig vor dem ersten DDL.
 *
 * `down()` laesst die Fremdschluessel ausdruecklich stehen. Genau das
 * Zurueckdrehen -- ein Fremdschluessel wird fuer ein Aufraeumen per SQL
 * gedroppt und nie wieder angelegt -- hat dieses Ticket verursacht.
 */
return new class extends Migration
{
    /**
     * Alle Fremdschluessel, die ein frischer Migrationslauf anlegt.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: string}>
     */
    private const EXPECTED_FOREIGN_KEYS = [
        ['addresses', 'user_id', 'users', 'id', 'restrict', 'restrict', 'addresses_user_id_foreign'],
        ['audit_logs', 'tenant_id', 'tenants', 'id', 'set null', 'restrict', 'audit_logs_tenant_id_foreign'],
        ['audit_logs', 'user_id', 'users', 'id', 'set null', 'restrict', 'audit_logs_user_id_foreign'],
        ['blog_posts', 'author_id', 'users', 'id', 'restrict', 'restrict', 'blog_posts_author_id_foreign'],
        ['blog_posts', 'blog_post_category_id', 'blog_post_categories', 'id', 'restrict', 'restrict', 'blog_posts_blog_post_category_id_foreign'],
        ['blog_posts', 'user_id', 'users', 'id', 'restrict', 'restrict', 'blog_posts_user_id_foreign'],
        ['buyer_profiles', 'tenant_id', 'tenants', 'id', 'cascade', 'restrict', 'buyer_profiles_tenant_id_foreign'],
        ['buyer_registrations', 'reviewed_by', 'users', 'id', 'set null', 'restrict', 'buyer_registrations_reviewed_by_foreign'],
        ['buyer_registrations', 'tenant_id', 'tenants', 'id', 'cascade', 'restrict', 'buyer_registrations_tenant_id_foreign'],
        ['call_attempts', 'lead_id', 'leads', 'id', 'cascade', 'restrict', 'call_attempts_lead_id_foreign'],
        ['call_attempts', 'lead_purchase_id', 'lead_purchases', 'id', 'cascade', 'restrict', 'call_attempts_lead_purchase_id_foreign'],
        ['call_attempts', 'tenant_id', 'tenants', 'id', 'cascade', 'restrict', 'call_attempts_tenant_id_foreign'],
        ['call_attempts', 'user_id', 'users', 'id', 'cascade', 'restrict', 'call_attempts_user_id_foreign'],
        ['caller_ids', 'tenant_id', 'tenants', 'id', 'cascade', 'restrict', 'caller_ids_tenant_id_foreign'],
        ['caller_ids', 'user_id', 'users', 'id', 'cascade', 'restrict', 'caller_ids_user_id_foreign'],
        ['discount_code_redemptions', 'discount_code_id', 'discount_codes', 'id', 'cascade', 'restrict', 'discount_code_redemptions_discount_code_id_foreign'],
        ['discount_code_redemptions', 'order_id', 'orders', 'id', 'set null', 'restrict', 'discount_code_redemptions_order_id_foreign'],
        ['discount_code_redemptions', 'subscription_id', 'subscriptions', 'id', 'cascade', 'restrict', 'discount_code_redemptions_subscription_id_foreign'],
        ['discount_code_redemptions', 'user_id', 'users', 'id', 'cascade', 'restrict', 'discount_code_redemptions_user_id_foreign'],
        ['discount_codes', 'discount_id', 'discounts', 'id', 'cascade', 'restrict', 'discount_codes_discount_id_foreign'],
        ['discount_codes', 'referral_reward_id', 'referral_rewards', 'id', 'cascade', 'restrict', 'discount_codes_referral_reward_id_foreign'],
        ['discount_one_time_product', 'discount_id', 'discounts', 'id', 'cascade', 'restrict', 'discount_one_time_product_discount_id_foreign'],
        ['discount_one_time_product', 'one_time_product_id', 'one_time_products', 'id', 'cascade', 'restrict', 'discount_one_time_product_one_time_product_id_foreign'],
        ['discount_payment_provider_data', 'discount_id', 'discounts', 'id', 'cascade', 'restrict', 'discount_payment_provider_data_discount_id_foreign'],
        ['discount_payment_provider_data', 'payment_provider_id', 'payment_providers', 'id', 'cascade', 'restrict', 'discount_payment_provider_data_payment_provider_id_foreign'],
        ['discount_plan', 'discount_id', 'discounts', 'id', 'cascade', 'restrict', 'discount_plan_discount_id_foreign'],
        ['discount_plan', 'plan_id', 'plans', 'id', 'cascade', 'restrict', 'discount_plan_plan_id_foreign'],
        ['funnel_conditions', 'funnel_id', 'funnels', 'id', 'cascade', 'restrict', 'funnel_conditions_funnel_id_foreign'],
        ['funnel_conditions', 'source_question_id', 'funnel_questions', 'id', 'cascade', 'restrict', 'funnel_conditions_source_question_id_foreign'],
        ['funnel_conditions', 'target_step_id', 'funnel_steps', 'id', 'cascade', 'restrict', 'funnel_conditions_target_step_id_foreign'],
        ['funnel_options', 'question_id', 'funnel_questions', 'id', 'cascade', 'restrict', 'funnel_options_question_id_foreign'],
        ['funnel_origins', 'funnel_id', 'funnels', 'id', 'cascade', 'restrict', 'funnel_origins_funnel_id_foreign'],
        ['funnel_questions', 'funnel_id', 'funnels', 'id', 'cascade', 'restrict', 'funnel_questions_funnel_id_foreign'],
        ['funnel_questions', 'step_id', 'funnel_steps', 'id', 'cascade', 'restrict', 'funnel_questions_step_id_foreign'],
        ['funnel_results', 'funnel_id', 'funnels', 'id', 'cascade', 'restrict', 'funnel_results_funnel_id_foreign'],
        ['funnel_steps', 'funnel_id', 'funnels', 'id', 'cascade', 'restrict', 'funnel_steps_funnel_id_foreign'],
        ['funnel_themes', 'funnel_id', 'funnels', 'id', 'cascade', 'restrict', 'funnel_themes_funnel_id_foreign'],
        ['funnel_versions', 'funnel_id', 'funnels', 'id', 'cascade', 'restrict', 'funnel_versions_funnel_id_foreign'],
        ['funnel_versions', 'published_by', 'users', 'id', 'set null', 'restrict', 'funnel_versions_published_by_foreign'],
        ['funnel_webhooks', 'funnel_id', 'funnels', 'id', 'cascade', 'restrict', 'funnel_webhooks_funnel_id_foreign'],
        ['funnels', 'current_version_id', 'funnel_versions', 'id', 'set null', 'restrict', 'funnels_current_version_id_foreign'],
        ['funnels', 'tenant_id', 'tenants', 'id', 'cascade', 'restrict', 'funnels_tenant_id_foreign'],
        ['invitations', 'tenant_id', 'tenants', 'id', 'restrict', 'restrict', 'invitations_tenant_id_foreign'],
        ['invitations', 'user_id', 'users', 'id', 'restrict', 'restrict', 'invitations_user_id_foreign'],
        ['invoices', 'transaction_id', 'transactions', 'id', 'cascade', 'restrict', 'invoices_transaction_id_foreign'],
        ['lead_answers', 'lead_id', 'leads', 'id', 'cascade', 'restrict', 'lead_answers_lead_id_foreign'],
        ['lead_complaints', 'buyer_tenant_id', 'tenants', 'id', 'cascade', 'restrict', 'lead_complaints_buyer_tenant_id_foreign'],
        ['lead_complaints', 'lead_id', 'leads', 'id', 'cascade', 'restrict', 'lead_complaints_lead_id_foreign'],
        ['lead_complaints', 'lead_purchase_id', 'lead_purchases', 'id', 'cascade', 'restrict', 'lead_complaints_lead_purchase_id_foreign'],
        ['lead_complaints', 'reviewed_by', 'users', 'id', 'set null', 'restrict', 'lead_complaints_reviewed_by_foreign'],
        ['lead_exports', 'requested_by', 'users', 'id', 'set null', 'restrict', 'lead_exports_requested_by_foreign'],
        ['lead_exports', 'tenant_id', 'tenants', 'id', 'cascade', 'restrict', 'lead_exports_tenant_id_foreign'],
        ['lead_purchases', 'buyer_tenant_id', 'tenants', 'id', 'cascade', 'restrict', 'lead_purchases_buyer_tenant_id_foreign'],
        ['lead_purchases', 'lead_id', 'leads', 'id', 'restrict', 'restrict', 'lead_purchases_lead_id_foreign'],
        ['lead_purchases', 'purchased_by_user_id', 'users', 'id', 'set null', 'restrict', 'lead_purchases_purchased_by_user_id_foreign'],
        ['lead_purchases', 'seller_tenant_id', 'tenants', 'id', 'cascade', 'restrict', 'lead_purchases_seller_tenant_id_foreign'],
        ['lead_state_log', 'actor_id', 'users', 'id', 'set null', 'restrict', 'lead_state_log_actor_id_foreign'],
        ['lead_state_log', 'lead_id', 'leads', 'id', 'cascade', 'restrict', 'lead_state_log_lead_id_foreign'],
        ['lead_watchlist', 'lead_id', 'leads', 'id', 'cascade', 'restrict', 'lead_watchlist_lead_id_foreign'],
        ['lead_watchlist', 'tenant_id', 'tenants', 'id', 'cascade', 'restrict', 'lead_watchlist_tenant_id_foreign'],
        ['leads', 'duplicate_of_lead_id', 'leads', 'id', 'set null', 'restrict', 'leads_duplicate_of_lead_id_foreign'],
        ['leads', 'funnel_id', 'funnels', 'id', 'set null', 'restrict', 'leads_funnel_id_foreign'],
        ['leads', 'funnel_version_id', 'funnel_versions', 'id', 'set null', 'restrict', 'leads_funnel_version_id_foreign'],
        ['leads', 'public_session_id', 'public_sessions', 'id', 'set null', 'restrict', 'leads_public_session_id_foreign'],
        ['leads', 'reserved_by', 'tenants', 'id', 'set null', 'restrict', 'leads_reserved_by_foreign'],
        ['leads', 'tenant_id', 'tenants', 'id', 'cascade', 'restrict', 'leads_tenant_id_foreign'],
        ['metric_data', 'metric_id', 'metrics', 'id', 'cascade', 'restrict', 'metric_data_metric_id_foreign'],
        ['model_has_permissions', 'permission_id', 'permissions', 'id', 'cascade', 'restrict', 'model_has_permissions_permission_id_foreign'],
        ['model_has_roles', 'role_id', 'roles', 'id', 'cascade', 'restrict', 'model_has_roles_role_id_foreign'],
        ['onboarding_mails_sent', 'tenant_id', 'tenants', 'id', 'cascade', 'restrict', 'onboarding_mails_sent_tenant_id_foreign'],
        ['one_time_product_payment_provider_data', 'one_time_product_id', 'one_time_products', 'id', 'restrict', 'restrict', 'one_time_product_payment_264vc5'],
        ['one_time_product_payment_provider_data', 'payment_provider_id', 'payment_providers', 'id', 'restrict', 'restrict', 'one_time_product_payment_264vc6'],
        ['one_time_product_price_payment_provider_data', 'one_time_product_price_id', 'one_time_product_prices', 'id', 'restrict', 'restrict', 'one_time_product_payment_264vc7'],
        ['one_time_product_price_payment_provider_data', 'payment_provider_id', 'payment_providers', 'id', 'restrict', 'restrict', 'one_time_product_payment_264vc8'],
        ['one_time_product_prices', 'currency_id', 'currencies', 'id', 'cascade', 'cascade', 'one_time_product_prices_currency_id_foreign'],
        ['one_time_product_prices', 'one_time_product_id', 'one_time_products', 'id', 'restrict', 'restrict', 'one_time_product_prices_one_time_product_id_foreign'],
        ['order_discounts', 'discount_id', 'discounts', 'id', 'cascade', 'restrict', 'order_discounts_discount_id_foreign'],
        ['order_discounts', 'order_id', 'orders', 'id', 'cascade', 'restrict', 'order_discounts_order_id_foreign'],
        ['order_items', 'currency_id', 'currencies', 'id', 'restrict', 'restrict', 'order_items_currency_id_foreign'],
        ['order_items', 'one_time_product_id', 'one_time_products', 'id', 'restrict', 'restrict', 'order_items_one_time_product_id_foreign'],
        ['order_items', 'order_id', 'orders', 'id', 'restrict', 'restrict', 'order_items_order_id_foreign'],
        ['orders', 'currency_id', 'currencies', 'id', 'restrict', 'restrict', 'orders_currency_id_foreign'],
        ['orders', 'payment_provider_id', 'payment_providers', 'id', 'restrict', 'restrict', 'orders_payment_provider_id_foreign'],
        ['orders', 'tenant_id', 'tenants', 'id', 'restrict', 'restrict', 'orders_tenant_id_foreign'],
        ['orders', 'user_id', 'users', 'id', 'restrict', 'restrict', 'orders_user_id_foreign'],
        ['payment_methods', 'wallet_id', 'wallets', 'id', 'cascade', 'restrict', 'payment_methods_wallet_id_foreign'],
        ['payout_requests', 'processed_by', 'users', 'id', 'set null', 'restrict', 'payout_requests_processed_by_foreign'],
        ['payout_requests', 'wallet_id', 'wallets', 'id', 'cascade', 'restrict', 'payout_requests_wallet_id_foreign'],
        ['phone_verification_attempts', 'user_id', 'users', 'id', 'set null', 'restrict', 'phone_verification_attempts_user_id_foreign'],
        ['plan_meter_payment_provider_data', 'payment_provider_id', 'payment_providers', 'id', 'restrict', 'restrict', 'plan_meter_payment_provider_data_payment_provider_id_foreign'],
        ['plan_meter_payment_provider_data', 'plan_meter_id', 'plan_meters', 'id', 'restrict', 'restrict', 'plan_meter_payment_provider_data_plan_meter_id_foreign'],
        ['plan_payment_provider_data', 'payment_provider_id', 'payment_providers', 'id', 'restrict', 'restrict', 'plan_payment_provider_data_payment_provider_id_foreign'],
        ['plan_payment_provider_data', 'plan_id', 'plans', 'id', 'restrict', 'restrict', 'plan_payment_provider_data_plan_id_foreign'],
        ['plan_price_payment_provider_data', 'payment_provider_id', 'payment_providers', 'id', 'restrict', 'restrict', 'plan_price_payment_provider_data_payment_provider_id_foreign'],
        ['plan_price_payment_provider_data', 'plan_price_id', 'plan_prices', 'id', 'restrict', 'restrict', 'plan_price_payment_provider_data_plan_price_id_foreign'],
        ['plan_prices', 'currency_id', 'currencies', 'id', 'cascade', 'cascade', 'plan_prices_currency_id_foreign'],
        ['plan_prices', 'plan_id', 'plans', 'id', 'cascade', 'cascade', 'plan_prices_plan_id_foreign'],
        ['plans', 'interval_id', 'intervals', 'id', 'cascade', 'cascade', 'plans_interval_id_foreign'],
        ['plans', 'meter_id', 'plan_meters', 'id', 'restrict', 'restrict', 'plans_meter_id_foreign'],
        ['plans', 'product_id', 'products', 'id', 'cascade', 'cascade', 'plans_product_id_foreign'],
        ['plans', 'trial_interval_id', 'intervals', 'id', 'cascade', 'cascade', 'plans_trial_interval_id_foreign'],
        ['postpaid_applications', 'decided_by', 'users', 'id', 'set null', 'restrict', 'postpaid_applications_decided_by_foreign'],
        ['postpaid_applications', 'wallet_id', 'wallets', 'id', 'cascade', 'restrict', 'postpaid_applications_wallet_id_foreign'],
        ['public_sessions', 'funnel_version_id', 'funnel_versions', 'id', 'cascade', 'restrict', 'public_sessions_funnel_version_id_foreign'],
        ['referral_codes', 'user_id', 'users', 'id', 'cascade', 'restrict', 'referral_codes_user_id_foreign'],
        ['referral_rewards', 'discount_code_id', 'discount_codes', 'id', 'set null', 'restrict', 'referral_rewards_discount_code_id_foreign'],
        ['referral_rewards', 'referral_id', 'referrals', 'id', 'cascade', 'restrict', 'referral_rewards_referral_id_foreign'],
        ['referral_rewards', 'referrer_user_id', 'users', 'id', 'cascade', 'restrict', 'referral_rewards_referrer_user_id_foreign'],
        ['referrals', 'referred_user_id', 'users', 'id', 'cascade', 'restrict', 'referrals_referred_user_id_foreign'],
        ['referrals', 'referrer_user_id', 'users', 'id', 'cascade', 'restrict', 'referrals_referrer_user_id_foreign'],
        ['roadmap_item_user_upvotes', 'roadmap_item_id', 'roadmap_items', 'id', 'cascade', 'restrict', 'roadmap_item_user_upvotes_roadmap_item_id_foreign'],
        ['roadmap_item_user_upvotes', 'user_id', 'users', 'id', 'cascade', 'restrict', 'roadmap_item_user_upvotes_user_id_foreign'],
        ['roadmap_items', 'user_id', 'users', 'id', 'cascade', 'restrict', 'roadmap_items_user_id_foreign'],
        ['role_has_permissions', 'permission_id', 'permissions', 'id', 'cascade', 'restrict', 'role_has_permissions_permission_id_foreign'],
        ['role_has_permissions', 'role_id', 'roles', 'id', 'cascade', 'restrict', 'role_has_permissions_role_id_foreign'],
        ['roles', 'tenant_id', 'tenants', 'id', 'restrict', 'restrict', 'roles_tenant_id_foreign'],
        ['session_events', 'session_id', 'public_sessions', 'id', 'cascade', 'restrict', 'session_events_session_id_foreign'],
        ['settlements', 'payment_method_id', 'payment_methods', 'id', 'set null', 'restrict', 'settlements_payment_method_id_foreign'],
        ['settlements', 'wallet_id', 'wallets', 'id', 'cascade', 'restrict', 'settlements_wallet_id_foreign'],
        ['subscription_discounts', 'discount_id', 'discounts', 'id', 'cascade', 'restrict', 'subscription_discounts_discount_id_foreign'],
        ['subscription_discounts', 'subscription_id', 'subscriptions', 'id', 'cascade', 'restrict', 'subscription_discounts_subscription_id_foreign'],
        ['subscription_usages', 'subscription_id', 'subscriptions', 'id', 'cascade', 'restrict', 'subscription_usages_subscription_id_foreign'],
        ['subscriptions', 'currency_id', 'currencies', 'id', 'restrict', 'restrict', 'subscriptions_currency_id_foreign'],
        ['subscriptions', 'interval_id', 'intervals', 'id', 'restrict', 'restrict', 'subscriptions_interval_id_foreign'],
        ['subscriptions', 'payment_provider_id', 'payment_providers', 'id', 'restrict', 'restrict', 'subscriptions_payment_provider_id_foreign'],
        ['subscriptions', 'plan_id', 'plans', 'id', 'restrict', 'restrict', 'subscriptions_plan_id_foreign'],
        ['subscriptions', 'tenant_id', 'tenants', 'id', 'restrict', 'restrict', 'subscriptions_tenant_id_foreign'],
        ['subscriptions', 'user_id', 'users', 'id', 'restrict', 'restrict', 'subscriptions_user_id_foreign'],
        ['team_tenant_user', 'team_id', 'teams', 'id', 'cascade', 'restrict', 'team_tenant_user_team_id_foreign'],
        ['team_tenant_user', 'tenant_user_id', 'tenant_user', 'id', 'cascade', 'restrict', 'team_tenant_user_tenant_user_id_foreign'],
        ['teams', 'tenant_id', 'tenants', 'id', 'restrict', 'restrict', 'teams_tenant_id_foreign'],
        ['telescope_entries_tags', 'entry_uuid', 'telescope_entries', 'uuid', 'cascade', 'restrict', 'telescope_entries_tags_entry_uuid_foreign'],
        ['tenant_onboarding_steps', 'completed_by_user_id', 'users', 'id', 'set null', 'restrict', 'tenant_onboarding_steps_completed_by_user_id_foreign'],
        ['tenant_onboarding_steps', 'tenant_id', 'tenants', 'id', 'cascade', 'restrict', 'tenant_onboarding_steps_tenant_id_foreign'],
        ['tenant_user', 'tenant_id', 'tenants', 'id', 'cascade', 'restrict', 'tenant_user_tenant_id_foreign'],
        ['tenant_user', 'user_id', 'users', 'id', 'cascade', 'restrict', 'tenant_user_user_id_foreign'],
        ['tenants', 'created_by', 'users', 'id', 'restrict', 'restrict', 'tenants_created_by_foreign'],
        ['transactions', 'currency_id', 'currencies', 'id', 'cascade', 'restrict', 'transactions_currency_id_foreign'],
        ['transactions', 'order_id', 'orders', 'id', 'restrict', 'restrict', 'transactions_order_id_foreign'],
        ['transactions', 'payment_provider_id', 'payment_providers', 'id', 'cascade', 'restrict', 'transactions_payment_provider_id_foreign'],
        ['transactions', 'subscription_id', 'subscriptions', 'id', 'cascade', 'restrict', 'transactions_subscription_id_foreign'],
        ['transactions', 'tenant_id', 'tenants', 'id', 'restrict', 'restrict', 'transactions_tenant_id_foreign'],
        ['transactions', 'user_id', 'users', 'id', 'cascade', 'restrict', 'transactions_user_id_foreign'],
        ['user_parameters', 'user_id', 'users', 'id', 'cascade', 'cascade', 'user_parameters_user_id_foreign'],
        ['user_stripe_data', 'tenant_id', 'tenants', 'id', 'restrict', 'restrict', 'user_stripe_data_tenant_id_foreign'],
        ['user_stripe_data', 'user_id', 'users', 'id', 'cascade', 'restrict', 'user_stripe_data_user_id_foreign'],
        ['user_subscription_trials', 'subscription_id', 'subscriptions', 'id', 'cascade', 'restrict', 'user_subscription_trials_subscription_id_foreign'],
        ['user_subscription_trials', 'user_id', 'users', 'id', 'cascade', 'restrict', 'user_subscription_trials_user_id_foreign'],
        ['wallet_transactions', 'created_by', 'users', 'id', 'set null', 'restrict', 'wallet_transactions_created_by_foreign'],
        ['wallet_transactions', 'wallet_id', 'wallets', 'id', 'cascade', 'restrict', 'wallet_transactions_wallet_id_foreign'],
        ['wallets', 'owner_id', 'tenants', 'id', 'cascade', 'restrict', 'wallets_owner_id_foreign'],
        ['wallets', 'postpaid_enabled_by', 'users', 'id', 'set null', 'restrict', 'wallets_postpaid_enabled_by_foreign'],
        ['webhook_deliveries', 'webhook_id', 'funnel_webhooks', 'id', 'cascade', 'restrict', 'webhook_deliveries_webhook_id_foreign'],
    ];

    public function up(): void
    {
        $missing = [];

        foreach (self::EXPECTED_FOREIGN_KEYS as $expected) {
            [$table, $column, $referencedTable] = $expected;

            if (! Schema::hasTable($table) || ! Schema::hasTable($referencedTable)) {
                $this->report("{$table}.{$column}: Tabelle fehlt, uebersprungen.");

                continue;
            }

            if (! Schema::hasColumn($table, $column)) {
                $this->report("{$table}.{$column}: Spalte fehlt, uebersprungen.");

                continue;
            }

            if ($this->hasForeignKeyOnColumn($table, $column)) {
                continue;
            }

            $missing[] = $expected;
        }

        if ($missing === []) {
            $this->report('Alle Fremdschluessel sind vorhanden.');

            return;
        }

        [$addable, $blocked] = $this->partitionByOrphanedRows($missing);

        foreach ($addable as [$table, $column, $referencedTable, $referencedColumn, $onDelete, $onUpdate, $name]) {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $referencedTable, $referencedColumn, $onDelete, $onUpdate, $name) {
                $foreignKey = $blueprint->foreign($column, $name)
                    ->references($referencedColumn)
                    ->on($referencedTable);

                match ($onDelete) {
                    'cascade' => $foreignKey->cascadeOnDelete(),
                    'set null' => $foreignKey->nullOnDelete(),
                    default => $foreignKey->restrictOnDelete(),
                };

                if ($onUpdate === 'cascade') {
                    $foreignKey->cascadeOnUpdate();
                }
            });

            $this->report("{$table}.{$column} -> {$referencedTable}.{$referencedColumn} ({$onDelete}) nachgezogen.");
        }

        $this->report(count($addable).' von '.count($missing).' fehlenden Fremdschluesseln nachgezogen.');

        if ($blocked !== []) {
            $this->report(
                count($blocked).' Fremdschluessel bleiben offen, weil verwaiste Zeilen darauf zeigen '
                ."(Ticket #83, Bereinigung in #87):\n  - ".implode("\n  - ", $blocked)
            );
        }
    }

    /**
     * Fremdschluessel bleiben stehen -- siehe Kopfkommentar.
     */
    public function down(): void
    {
        $this->report('down() laesst die nachgezogenen Fremdschluessel absichtlich stehen.');
    }

    /**
     * Trennt die fehlenden Fremdschluessel in anlegbare und durch verwaiste
     * Zeilen blockierte.
     *
     * Der Scan laeuft vollstaendig vor dem ersten DDL, damit der Bericht nicht
     * von halb angelegten Fremdschluesseln abhaengt. Gemeldet wird je Beziehung,
     * wie viele Zeilen auf wie viele fehlende Werte zeigen, dazu bis zu fuenf
     * Beispielwerte, damit der Fall ueber den Domaenenweg aufgeloest werden
     * kann. Geloescht wird nichts: Welche Zeile Beleg und welche Muell ist,
     * entscheidet die Fachlogik, nicht eine Migration.
     *
     * Blockierte Beziehungen werden uebersprungen, nicht abgebrochen. Ein
     * Abbruch wuerde auf dem Live-Stand jede folgende Migration aufhalten, und
     * zwar wegen Altdaten, die nichts mit dem naechsten Feature zu tun haben.
     * Der naechste Lauf nach der Bereinigung zieht sie nach -- die Migration ist
     * idempotent.
     *
     * @param  list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: string}>  $missing
     * @return array{0: list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: string}>, 1: list<string>}
     */
    private function partitionByOrphanedRows(array $missing): array
    {
        $addable = [];
        $problems = [];

        foreach ($missing as $expected) {
            [$table, $column, $referencedTable, $referencedColumn] = $expected;
            // Das Elternteil wird benannt (`as parent`): Bei einem Selbstbezug
            // -- `leads.duplicate_of_lead_id` zeigt auf `leads.id` -- loesen
            // sonst beide Spalten der Unterabfrage auf dieselbe innere Tabelle
            // auf. Die Bedingung fragte dann "gibt es einen Lead, der sein
            // eigenes Duplikat ist", und jede Zeile zaehlte als verwaist.
            $orphans = DB::table($table)
                ->whereNotNull("{$table}.{$column}")
                ->whereNotExists(fn ($query) => $query
                    ->from($referencedTable, 'parent')
                    ->whereColumn("parent.{$referencedColumn}", "{$table}.{$column}"))
                ->groupBy("{$table}.{$column}")
                ->orderBy("{$table}.{$column}")
                ->pluck(DB::raw('COUNT(*)'), "{$table}.{$column}")
                ->all();

            if ($orphans === []) {
                $addable[] = $expected;

                continue;
            }

            $examples = [];

            foreach (array_slice($orphans, 0, 5, true) as $value => $rows) {
                $examples[] = $value.' ('.$this->rowCount((int) $rows).')';
            }

            $rest = count($orphans) > 5 ? ' und '.(count($orphans) - 5).' weitere' : '';

            $rows = (int) array_sum($orphans);
            $values = count($orphans);

            $problems[] = "{$table}.{$column} -> {$referencedTable}.{$referencedColumn}: "
                .$this->rowCount($rows).($rows === 1 ? ' zeigt' : ' zeigen').' auf '
                .($values === 1 ? 'einen fehlenden Wert' : "{$values} fehlende Werte")
                .': '.implode(', ', $examples).$rest;
        }

        return [$addable, $problems];
    }

    private function rowCount(int $rows): string
    {
        return $rows === 1 ? '1 Zeile' : "{$rows} Zeilen";
    }

    private function hasForeignKeyOnColumn(string $table, string $column): bool
    {
        return collect(Schema::getForeignKeys($table))
            ->contains(fn (array $key): bool => $key['columns'] === [$column]);
    }

    private function report(string $message): void
    {
        if (app()->runningInConsole()) {
            fwrite(STDOUT, "  {$message}\n");
        }
    }
};
