<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Feeds the navbar search palette (the Ctrl+K popup).
 *
 * Vuexy's main.js fetches `{assets}/json/search-vertical.json` and filters it
 * in the browser, so this is served from a route rather than a static file:
 * an admin and a dispatcher must not be offered each other's pages, and a
 * panel user must not be offered pages their role would refuse.
 */
class SearchPaletteController extends Controller
{
    public function vertical(): JsonResponse
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json(['navigation' => [], 'suggestions' => []]);
        }

        [$navigation, $suggestions] = $user->isAdmin()
            ? $this->forAdmin()
            : $this->forDispatcher($user);

        return response()->json([
            'navigation'  => $navigation,
            'suggestions' => $suggestions,
        ]);
    }

    /**
     * @return array{0: array<string, array<int, array<string, string>>>, 1: array<string, array<int, array<string, string>>>}
     */
    private function forAdmin(): array
    {
        $navigation = [
            'Platform' => [
                ['name' => 'System Overview',      'icon' => 'tabler-layout-dashboard', 'url' => 'admin'],
                ['name' => 'Platform Security',    'icon' => 'tabler-shield-lock',      'url' => 'admin/security'],
            ],
            'Companies' => [
                ['name' => 'Company Management',   'icon' => 'tabler-building',         'url' => 'admin/companies'],
                ['name' => 'Register Company',     'icon' => 'tabler-building-plus',    'url' => 'admin/companies/create'],
                ['name' => 'Archived Companies',   'icon' => 'tabler-archive',          'url' => 'admin/companies/archived'],
            ],
            'Access' => [
                ['name' => 'Role Management',      'icon' => 'tabler-id-badge-2',       'url' => 'admin/roles'],
                ['name' => 'Create Role',          'icon' => 'tabler-plus',             'url' => 'admin/roles/create'],
            ],
            'Billing' => [
                ['name' => 'Subscription Plans',   'icon' => 'tabler-file-dollar',      'url' => 'admin/subscription'],
                ['name' => 'Create Plan',          'icon' => 'tabler-plus',             'url' => 'admin/subscription/create'],
            ],
        ];

        $suggestions = [
            'Popular Searches' => [
                ['name' => 'Companies',          'icon' => 'tabler-building',         'url' => 'admin/companies'],
                ['name' => 'Roles',              'icon' => 'tabler-id-badge-2',       'url' => 'admin/roles'],
                ['name' => 'Subscription Plans', 'icon' => 'tabler-file-dollar',      'url' => 'admin/subscription'],
            ],
            'Platform' => [
                ['name' => 'System Overview',    'icon' => 'tabler-layout-dashboard', 'url' => 'admin'],
                ['name' => 'Platform Security',  'icon' => 'tabler-shield-lock',      'url' => 'admin/security'],
                ['name' => 'Archived Companies', 'icon' => 'tabler-archive',          'url' => 'admin/companies/archived'],
            ],
        ];

        return [$navigation, $suggestions];
    }

    /**
     * @return array{0: array<string, array<int, array<string, string>>>, 1: array<string, array<int, array<string, string>>>}
     */
    private function forDispatcher(User $user): array
    {
        // Each entry names the permission it needs, so a Read-Only Analyst is
        // never offered a page that would 403 on arrival.
        $all = [
            'Operations' => [
                ['name' => 'Dispatch Board',       'icon' => 'tabler-map-2',            'url' => 'dispatcher',                 'perm' => null],
                ['name' => 'Live Map Operations',  'icon' => 'tabler-map-pin',          'url' => 'dispatcher/live-map',        'perm' => 'live_map.view'],
                ['name' => 'Smart Auto-Dispatch',  'icon' => 'tabler-route-2',          'url' => 'dispatcher/auto-dispatch',   'perm' => 'auto_dispatch.run'],
                ['name' => 'Incidents & Alerts',   'icon' => 'tabler-urgent',           'url' => 'dispatcher/incidents',       'perm' => 'incidents.view'],
            ],
            'Trips' => [
                ['name' => 'Trip List',            'icon' => 'tabler-file-text',        'url' => 'dispatcher/trip/list',       'perm' => 'trips.view'],
                ['name' => 'Calendar View',        'icon' => 'tabler-calendar',         'url' => 'dispatcher/trip/calendar',   'perm' => 'trips.view'],
                ['name' => 'Create Trip',          'icon' => 'tabler-plus',             'url' => 'dispatcher/trip/create',     'perm' => 'trips.create'],
            ],
            'People' => [
                ['name' => 'Driver Management',    'icon' => 'tabler-users',            'url' => 'dispatcher/driver/list',     'perm' => 'drivers.view'],
                ['name' => 'Add Driver',           'icon' => 'tabler-user-plus',        'url' => 'dispatcher/driver/create',   'perm' => 'drivers.manage'],
                ['name' => 'Client Profiles',      'icon' => 'tabler-user-square',      'url' => 'dispatcher/client',          'perm' => 'clients.view'],
                ['name' => 'Add Client',           'icon' => 'tabler-user-plus',        'url' => 'dispatcher/client/create',   'perm' => 'clients.manage'],
            ],
            'Fleet' => [
                ['name' => 'Fleet Management',     'icon' => 'tabler-car',              'url' => 'dispatcher/fleet',           'perm' => 'fleet.view'],
                ['name' => 'Add Vehicle',         'icon' => 'tabler-plus',             'url' => 'dispatcher/fleet/create',    'perm' => 'fleet.manage'],
                ['name' => 'Compliance Center',    'icon' => 'tabler-shield-check',     'url' => 'dispatcher/compliance',      'perm' => 'drivers.view'],
            ],
            'Communication' => [
                ['name' => 'Driver Messages',      'icon' => 'tabler-message-circle',   'url' => 'dispatcher/messages',        'perm' => 'messages.view'],
                ['name' => 'Notifications',        'icon' => 'tabler-bell',             'url' => 'dispatcher/notifications',   'perm' => null],
            ],
            'Insights' => [
                ['name' => 'Advanced Search',      'icon' => 'tabler-zoom-check',       'url' => 'dispatcher/search',          'perm' => 'trips.view'],
                ['name' => 'Reports',              'icon' => 'tabler-chart-histogram',  'url' => 'dispatcher/reports',         'perm' => 'reports.view'],
            ],
            'Settings' => [
                ['name' => 'System Users',         'icon' => 'tabler-users-group',      'url' => 'dispatcher/users',           'perm' => 'users.manage'],
                ['name' => 'Settings',             'icon' => 'tabler-settings',         'url' => 'dispatcher/settings',        'perm' => 'settings.manage'],
                ['name' => 'My Profile',           'icon' => 'tabler-user',             'url' => 'dispatcher/profile',         'perm' => null],
            ],
        ];

        $navigation = [];

        foreach ($all as $section => $items) {
            $allowed = array_values(array_filter(
                $items,
                fn (array $item) => $item['perm'] === null || $user->hasPermission($item['perm'])
            ));

            if ($allowed === []) {
                continue;
            }

            // The permission key is an implementation detail; strip it before
            // the list reaches the browser.
            $navigation[$section] = array_map(
                fn (array $item) => ['name' => $item['name'], 'icon' => $item['icon'], 'url' => $item['url']],
                $allowed
            );
        }

        // The empty-query panel: the handful of places people actually go.
        $suggestions = [];

        foreach (['Trips', 'Operations', 'People', 'Insights'] as $section) {
            if (isset($navigation[$section])) {
                $suggestions[$section] = array_slice($navigation[$section], 0, 4);
            }
        }

        return [$navigation, $suggestions];
    }
}
