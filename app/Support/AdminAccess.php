<?php

namespace App\Support;

/** Role gate for the admin. owner > manager > staff. Legacy is_admin without a role = owner. */
class AdminAccess
{
    public const ROLES = ['owner' => 'Owner', 'manager' => 'Manager', 'staff' => 'Staff'];

    /**
     * Sections each role may open (nav keys + admin.section route gates). Managers lose settings/users;
     * staff see fulfilment only. Money/bulk actions are their own sections so staff can work an order
     * without being able to: refunds (Stripe), purchases (freight trucks, PTI orders), labels (real postage — staff ship orders, owner 2026-11-01),
     * catalog_write (bulk edit, CSV import, deletes), exports (CSV dumps with customer PII / pricing).
     */
    private const ALLOW = [
        'staff' => ['dashboard', 'orders', 'chats', 'driveshafts', 'inventory', 'customers', 'leads', 'rma', 'products', 'categories', 'designs', 'labels'],
        'manager' => ['dashboard', 'orders', 'chats', 'driveshafts', 'inventory', 'customers', 'leads', 'rma', 'products', 'categories', 'designs',
            'coupons', 'wholesale', 'recovery', 'reports', 'activity', 'refunds', 'purchases', 'labels', 'catalog_write', 'exports', 'seo'],
    ];

    public static function role($user = null): string
    {
        $user ??= auth()->user();
        if (!$user || !$user->is_admin) return '';
        return $user->admin_role ?: 'owner';
    }

    public static function can(string $section, $user = null): bool
    {
        $role = self::role($user);
        if ($role === '') return false;
        if ($role === 'owner') return true;
        return in_array($section, self::ALLOW[$role] ?? [], true);
    }
}
