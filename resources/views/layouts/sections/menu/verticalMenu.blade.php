@php
use Illuminate\Support\Facades\Route;
$configData = Helper::appClasses();
$user = auth()->user();

// Dynamic role-based menu items
if ($user && $user->isAdmin()) {
    $menuItems = [
        (object)[
            'url' => 'admin',
            'name' => 'System Overview',
            'icon' => 'menu-icon icon-base ti tabler-layout-dashboard',
            'slug' => 'admin.dashboard'
        ],
        (object)[
            'url' => 'admin/companies',
            'name' => 'Company Management',
            'icon' => 'menu-icon icon-base ti tabler-building',
            'slug' => ['admin.company.list', 'admin.company.create', 'admin.company.show', 'admin.company.edit', 'admin.company.archived']
        ],
        (object)[
            'url' => 'admin/roles',
            'name' => 'Role Management',
            'icon' => 'menu-icon icon-base ti tabler-id-badge-2',
            'slug' => ['admin.role.list', 'admin.role.create', 'admin.role.edit']
        ],
        (object)[
            'url' => 'admin/subscription',
            'name' => 'My Subscription',
            'icon' => 'menu-icon icon-base ti tabler-file-dollar',
            'slug' => 'admin.subscription'
        ],
        (object)[
            'url' => 'admin/security',
            'name' => 'Platform Security',
            'icon' => 'menu-icon icon-base ti tabler-shield-lock',
            'slug' => 'admin.security'
        ]
    ];
} elseif ($user && $user->isDispatcher()) {
    $menuItems = [
        (object)[
            'url' => 'dispatcher',
            'name' => 'Dispatch Board',
            'icon' => 'menu-icon icon-base ti tabler-map-2',
            'slug' => 'dispatcher.dashboard'
        ],
        (object)[
            'name' => 'Trip Management',
            'permission' => 'trips.view',
            'icon' => 'menu-icon icon-base ti tabler-layout-grid',
            'slug' => 'dispatcher.trip',
            'submenu' => [
                (object)[
                    'url' => 'dispatcher/trip/list',
                    'permission' => 'trips.view',
                    'name' => 'Trip List',
                    'icon' => 'menu-icon icon-base ti tabler-file-text',
                    'slug' => ['dispatcher.trip.list', 'dispatcher.trip.details', 'dispatcher.trip.edit']
                ],
                (object)[
                    'url' => 'dispatcher/trip/calendar',
                    'permission' => 'trips.view',
                    'name' => 'Calendar View',
                    'icon' => 'menu-icon icon-base ti tabler-calendar',
                    'slug' => 'dispatcher.trip.calendar'
                ],
                (object)[
                    'url' => 'dispatcher/trip/create',
                    'permission' => 'trips.create',
                    'name' => 'Create Trip',
                    'icon' => 'menu-icon icon-base ti tabler-plus',
                    'slug' => 'dispatcher.trip.create'
                ],
            ]
        ],
        (object)[
            'url' => 'dispatcher/live-map',
            'permission' => 'live_map.view',
            'name' => 'Live Map Operations',
            'icon' => 'menu-icon icon-base ti tabler-clipboard-data',
            'slug' => 'dispatcher.live-map'
        ],
        (object)[
            'url' => 'dispatcher/auto-dispatch',
            'permission' => 'auto_dispatch.run',
            'name' => 'Smart Auto-Dispatch',
            'icon' => 'menu-icon icon-base ti tabler-route-2',
            'slug' => 'dispatcher.auto-dispatch'
        ],
        (object)[
            'url' => 'dispatcher/fleet',
            'permission' => 'fleet.view',
            'name' => 'Fleet Management',
            'icon' => 'menu-icon icon-base ti tabler-chart-bar',
            'slug' => 'dispatcher.fleet.index'
        ],
        (object)[
            'url' => 'dispatcher/driver/list',
            'permission' => 'drivers.view',
            'name' => 'Driver Management',
            'icon' => 'menu-icon icon-base ti tabler-users',
            'slug' => 'dispatcher.driver'
        ],
        (object)[
            'url' => 'dispatcher/client',
            'permission' => 'clients.view',
            'name' => 'Client Profiles',
            'icon' => 'menu-icon icon-base ti tabler-user-square',
            'slug' => ['dispatcher.client.index', 'dispatcher.client.show', 'dispatcher.client.create', 'dispatcher.client.edit']
        ],
        (object)[
            'url' => 'dispatcher/messages',
            'permission' => 'messages.view',
            'name' => 'Driver Messages',
            'icon' => 'menu-icon icon-base ti tabler-message-circle',
            'slug' => ['dispatcher.messages.index', 'dispatcher.messages.thread']
        ],
        (object)[
            'url' => 'dispatcher/incidents',
            'permission' => 'incidents.view',
            'name' => 'Incidents & Alerts',
            'icon' => 'menu-icon icon-base ti tabler-urgent',
            'slug' => ['dispatcher.incidents.index', 'dispatcher.incidents.show']
        ],
        (object)[
            'url' => 'dispatcher/compliance',
            'permission' => 'drivers.view',
            'name' => 'Compliance Center',
            'icon' => 'menu-icon icon-base ti tabler-shield-check',
            'slug' => ['dispatcher.compliance', 'dispatcher.compliance.driver']
        ],
        (object)[
            'url' => 'dispatcher/reports',
            'permission' => 'reports.view',
            'name' => 'Reports',
            'icon' => 'menu-icon icon-base ti tabler-chart-histogram',
            'slug' => 'dispatcher.reports'
        ],
        (object)[
            'url' => 'dispatcher/billing',
            'permission' => 'billing.view',
            'name' => 'Billing & Claims',
            'icon' => 'menu-icon icon-base ti tabler-file-dollar',
            'slug' => ['dispatcher.billing.index', 'dispatcher.billing.show', 'dispatcher.billing.create', 'dispatcher.billing.unbilled']
        ],
        (object)[
            'url' => 'dispatcher/subscription',
            'name' => 'My Subscription',
            'icon' => 'menu-icon icon-base ti tabler-credit-card',
            'slug' => 'dispatcher.subscription'
        ],
        (object)[
            'name' => 'Settings',
            'icon' => 'menu-icon icon-base ti tabler-settings',
            'slug' => ['dispatcher.settings', 'dispatcher.users'],
            'submenu' => [
                (object)[
                    'url' => 'dispatcher/settings',
                    'permission' => 'settings.manage',
                    'name' => 'Company Settings',
                    'icon' => 'menu-icon icon-base ti tabler-adjustments',
                    'slug' => 'dispatcher.settings'
                ],
                (object)[
                    'url' => 'dispatcher/users',
                    'permission' => 'users.manage',
                    'name' => 'System Users',
                    'icon' => 'menu-icon icon-base ti tabler-users-group',
                    'slug' => ['dispatcher.users.index', 'dispatcher.users.role.create', 'dispatcher.users.role.edit']
                ],
                (object)[
                    'url' => 'dispatcher/profile',
                    'name' => 'My Profile',
                    'icon' => 'menu-icon icon-base ti tabler-user',
                    'slug' => 'dispatcher.profile.index'
                ],
            ]
        ],
    ];
} else {
    $menuItems = isset($menuData[0]->menu) ? $menuData[0]->menu : [];
}

/*
 * Hide what the signed-in user's role would refuse.
 *
 * The routes already return 403, but a menu that lists them sends people into
 * a wall. Admins and company owners pass everything, so this only ever trims
 * the sidebar for added panel staff. A parent whose children are all hidden
 * disappears with them rather than opening onto nothing.
 */
$allowed = function ($item) use ($user) {
    if (! $user) {
        return false;
    }

    return empty($item->permission) || $user->hasPermission($item->permission);
};

$menuItems = collect($menuItems)
    ->filter($allowed)
    ->map(function ($item) use ($allowed) {
        if (! empty($item->submenu)) {
            $item = clone $item;
            $item->submenu = collect($item->submenu)->filter($allowed)->values()->all();
        }

        return $item;
    })
    ->filter(fn ($item) => empty($item->url) ? ! empty($item->submenu) : true)
    ->values()
    ->all();
@endphp

<aside id="layout-menu" class="layout-menu menu-vertical menu" @foreach ($configData['menuAttributes'] as $attribute => $value) {{ $attribute }}="{{ $value }}" @endforeach>

  <!-- ! Hide app brand if navbar-full -->
  @if (!isset($navbarFull))
  <div class="app-brand demo">
    <a href="{{ url('/') }}" class="app-brand-link">
      <span class="app-brand-logo demo">@include('_partials.macros')</span>
      <span class="app-brand-text demo menu-text fw-bold ms-3">{{ config('variables.templateName') }}</span>
    </a>

    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
      <i class="icon-base ti menu-toggle-icon d-none d-xl-block"></i>
      <i class="icon-base ti tabler-x d-block d-xl-none"></i>
    </a>
  </div>
  @endif

  <div class="menu-inner-shadow"></div>

  <ul class="menu-inner py-1">
    @foreach ($menuItems as $menu)

    {{-- menu headers --}}
    @if (isset($menu->menuHeader))
    <li class="menu-header small">
      <span class="menu-header-text">{{ __($menu->menuHeader) }}</span>
    </li>
    @else
    {{-- active menu method --}}
    @php
    $activeClass = null;
    $currentRouteName = Route::currentRouteName();

    if ($currentRouteName === $menu->slug) {
        $activeClass = 'active';
    } elseif (is_string($menu->slug) && str_starts_with($currentRouteName ?? '', $menu->slug)) {
        $activeClass = 'active';
    } elseif (is_array($menu->slug)) {
        foreach ($menu->slug as $slug) {
            if (str_starts_with($currentRouteName ?? '', $slug)) {
                $activeClass = 'active';
            }
        }
    }

    if (isset($menu->submenu)) {
        foreach ($menu->submenu as $sub) {
            if ($currentRouteName === $sub->slug || (is_string($sub->slug) && str_starts_with($currentRouteName ?? '', $sub->slug))) {
                $activeClass = 'active open';
                break;
            } elseif (is_array($sub->slug)) {
                foreach ($sub->slug as $subSlug) {
                    if ($currentRouteName === $subSlug || str_starts_with($currentRouteName ?? '', $subSlug)) {
                        $activeClass = 'active open';
                        break 2;
                    }
                }
            }
        }
        if (!$activeClass && is_string($menu->slug) && str_starts_with($currentRouteName ?? '', $menu->slug)) {
            $activeClass = 'active open';
        }
    }
    @endphp

    {{-- main menu --}}
    <li class="menu-item {{ $activeClass }}">
      <a href="{{ isset($menu->url) ? url($menu->url) : 'javascript:void(0);' }}"
        class="{{ isset($menu->submenu) ? 'menu-link menu-toggle' : 'menu-link' }}" @if (isset($menu->target) and !empty($menu->target)) target="_blank" @endif>
        @isset($menu->icon)
        <i class="{{ $menu->icon }}"></i>
        @endisset
        <div>{{ isset($menu->name) ? __($menu->name) : '' }}</div>
        @isset($menu->badge)
        <div class="badge bg-{{ $menu->badge[0] }} rounded-pill ms-auto">{{ $menu->badge[1] }}</div>
        @endisset
      </a>

      {{-- submenu --}}
      @isset($menu->submenu)
      @include('layouts.sections.menu.submenu', ['menu' => $menu->submenu])
      @endisset
    </li>
    @endif
    @endforeach
  </ul>

</aside>
