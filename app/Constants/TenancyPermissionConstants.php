<?php

namespace App\Constants;

class TenancyPermissionConstants
{
    public const TENANCY_PERMISSION_PREFIX = 'tenancy:';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_USER = 'user';

    /**
     * Rolle eines Nutzers in einem Kaeufer-Tenant (FB-002). Kaeufer verwalten
     * keine Funnels, sondern kaufen Leads ueber den Marktplatz.
     */
    public const ROLE_BUYER = 'buyer';

    public const TENANT_CREATOR_ROLE = self::ROLE_ADMIN;

    public const PERMISSION_CREATE_SUBSCRIPTIONS = 'tenancy: create subscriptions';

    public const PERMISSION_UPDATE_SUBSCRIPTIONS = 'tenancy: update subscriptions';

    public const PERMISSION_DELETE_SUBSCRIPTIONS = 'tenancy: delete subscriptions';

    public const PERMISSION_VIEW_SUBSCRIPTIONS = 'tenancy: view subscriptions';

    public const PERMISSION_CREATE_ORDERS = 'tenancy: create orders';

    public const PERMISSION_UPDATE_ORDERS = 'tenancy: update orders';

    public const PERMISSION_DELETE_ORDERS = 'tenancy: delete orders';

    public const PERMISSION_VIEW_ORDERS = 'tenancy: view orders';

    public const PERMISSION_VIEW_TRANSACTIONS = 'tenancy: view transactions';

    public const PERMISSION_INVITE_MEMBERS = 'tenancy: invite members';

    public const PERMISSION_MANAGE_TEAM = 'tenancy: manage team';

    public const PERMISSION_UPDATE_TENANT_SETTINGS = 'tenancy: update tenant settings';

    public const PERMISSION_VIEW_ROLES = 'tenancy: view roles';

    public const PERMISSION_CREATE_ROLES = 'tenancy: create roles';

    public const PERMISSION_UPDATE_ROLES = 'tenancy: update roles';

    public const PERMISSION_DELETE_ROLES = 'tenancy: delete roles';

    /**
     * Erlaubt das Anlegen und Widerrufen von API-Tokens des Tenants (FB-006).
     */
    public const PERMISSION_MANAGE_API_TOKENS = 'tenancy: manage api tokens';

    /**
     * Erlaubt die Volltextsuche ueber Kontaktdaten in der Lead-Liste (FB-034).
     *
     * Ohne diese Berechtigung sucht ein Mitglied nur ueber den Namen. Wer nicht
     * mit Kontaktdaten arbeitet, soll auch nicht ueber sie suchen koennen --
     * eine Suche ueber alle Telefonnummern waere ein Auszug der Datenbank in
     * kleinen Schritten.
     */
    public const PERMISSION_SEARCH_LEAD_CONTACTS = 'tenancy: search lead contacts';

    /**
     * Erlaubt das Anlegen und Bearbeiten von Funnels im Builder (FB-015).
     */
    public const PERMISSION_MANAGE_FUNNELS = 'tenancy: manage funnels';
}
