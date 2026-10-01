<?php

namespace App\Support;

/**
 * The catalogue of permissions a dispatcher-side role can grant.
 *
 * This is the single authority: role forms render from it, validation checks
 * against it, and User::hasPermission() answers from whatever a role stored.
 * Adding a capability to the panel means adding its key here first, so a
 * permission can never exist in a checkbox but nowhere else.
 */
class Permissions
{
    /**
     * Grouped for the role editor. The group label is only presentation; the
     * keys are what gets stored.
     *
     * @return array<string, array<string, string>>
     */
    public static function groups(): array
    {
        return [
            'Trips' => [
                'trips.view'   => 'View trips and the dispatch board',
                'trips.create' => 'Create trips',
                'trips.edit'   => 'Edit trips',
                'trips.assign' => 'Assign trips to drivers',
                'trips.cancel' => 'Cancel trips',
                'trips.delete' => 'Delete trips from the panel',
            ],
            'Drivers' => [
                'drivers.view'       => 'View drivers',
                'drivers.manage'     => 'Add, edit and remove drivers',
                'drivers.compliance' => 'Manage driver documents and compliance',
            ],
            'Clients' => [
                'clients.view'   => 'View client profiles',
                'clients.manage' => 'Add, edit and remove clients',
                'clients.notes'  => 'Add and edit client notes',
            ],
            'Fleet' => [
                'fleet.view'   => 'View vehicles',
                'fleet.manage' => 'Add, edit and remove vehicles',
            ],
            'Operations' => [
                'live_map.view'      => 'View the live operations map',
                'auto_dispatch.run'  => 'Run smart auto-dispatch',
                'incidents.view'     => 'View incident and SOS reports',
                'incidents.manage'   => 'Acknowledge and resolve incidents',
            ],
            'Communication' => [
                'messages.view' => 'Read driver conversations',
                'messages.send' => 'Send messages to drivers',
            ],
            'Business' => [
                'reports.view'       => 'View reports and analytics',
                'billing.view'       => 'View billing and claims',
                'billing.manage'     => 'Manage invoices and payments',
                'subscription.manage' => 'Manage the company subscription',
            ],
            'Administration' => [
                'users.manage'    => 'Manage panel users and their roles',
                'settings.manage' => 'Change company settings',
            ],
        ];
    }

    /**
     * Every valid permission key, flattened.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return array_keys(array_merge(...array_values(static::groups())));
    }

    public static function label(string $permission): string
    {
        foreach (static::groups() as $permissions) {
            if (isset($permissions[$permission])) {
                return $permissions[$permission];
            }
        }

        return $permission;
    }

    public static function exists(string $permission): bool
    {
        return in_array($permission, static::all(), true);
    }

    /**
     * Drop anything that is not a known permission. Role forms post whatever
     * the browser sends, so this runs before anything is stored.
     *
     * @param  array<int, mixed>  $permissions
     * @return array<int, string>
     */
    public static function clean(array $permissions): array
    {
        $clean = array_values(array_unique(array_filter(
            array_map(fn ($permission) => is_string($permission) ? $permission : null, $permissions),
            fn ($permission) => $permission !== null && static::exists($permission)
        )));

        sort($clean);

        return $clean;
    }

    /**
     * The roles the platform ships with, matching the wireframe's Head
     * Dispatcher, Standard Dispatcher and Read-Only Analyst.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function systemRoles(): array
    {
        $everything = static::all();

        return [
            [
                'name'        => 'Head Dispatcher',
                'slug'        => 'head-dispatcher',
                'description' => 'Full access to every part of the company panel, including users and billing.',
                'permissions' => $everything,
            ],
            [
                'name'        => 'Standard Dispatcher',
                'slug'        => 'standard-dispatcher',
                'description' => 'Runs day-to-day dispatch: trips, drivers, clients, fleet and messaging.',
                'permissions' => [
                    'trips.view', 'trips.create', 'trips.edit', 'trips.assign', 'trips.cancel',
                    'drivers.view', 'drivers.compliance',
                    'clients.view', 'clients.manage', 'clients.notes',
                    'fleet.view',
                    'live_map.view', 'auto_dispatch.run', 'incidents.view', 'incidents.manage',
                    'messages.view', 'messages.send',
                    'reports.view',
                ],
            ],
            [
                'name'        => 'Read-Only Analyst',
                'slug'        => 'read-only-analyst',
                'description' => 'Can see operations and reporting but cannot change anything.',
                'permissions' => [
                    'trips.view', 'drivers.view', 'clients.view', 'fleet.view',
                    'live_map.view', 'incidents.view', 'messages.view', 'reports.view',
                    'billing.view',
                ],
            ],
        ];
    }
}
