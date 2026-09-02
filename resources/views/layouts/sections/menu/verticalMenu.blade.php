@php
use Illuminate\Support\Facades\Route;
$configData = Helper::appClasses();
$user = auth()->user();

// Dynamic role-based menu items
if ($user && $user->isAdmin()) {
    $menuItems = [
        (object)[
            'url' => 'admin',
            'name' => 'Dashboard',
            'icon' => 'menu-icon icon-base ti tabler-dashboard',
            'slug' => 'admin.dashboard'
        ],
        (object)[
            'url' => 'admin/user/list',
            'name' => 'Users',
            'icon' => 'menu-icon icon-base ti tabler-users',
            'slug' => 'admin.user'
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
            'icon' => 'menu-icon icon-base ti tabler-layout-grid',
            'slug' => 'dispatcher.trip',
            'submenu' => [
                (object)[
                    'url' => 'dispatcher/trip/list',
                    'name' => 'Trip List',
                    'icon' => 'menu-icon icon-base ti tabler-file-text',
                    'slug' => ['dispatcher.trip.list', 'dispatcher.trip.details', 'dispatcher.trip.edit']
                ],
                (object)[
                    'url' => 'dispatcher/trip/calendar',
                    'name' => 'Calendar View',
                    'icon' => 'menu-icon icon-base ti tabler-calendar',
                    'slug' => 'dispatcher.trip.calendar'
                ],
                (object)[
                    'url' => 'dispatcher/trip/create',
                    'name' => 'Create Trip',
                    'icon' => 'menu-icon icon-base ti tabler-plus',
                    'slug' => 'dispatcher.trip.create'
                ],
            ]
        ],
        (object)[
            'url' => 'dispatcher/live-map',
            'name' => 'Live Map Operations',
            'icon' => 'menu-icon icon-base ti tabler-clipboard-data',
            'slug' => 'dispatcher.live-map'
        ],
        (object)[
            'url' => 'dispatcher/auto-dispatch',
            'name' => 'Smart Auto-Dispatch',
            'icon' => 'menu-icon icon-base ti tabler-route-2',
            'slug' => 'dispatcher.auto-dispatch'
        ],
        (object)[
            'url' => 'dispatcher/fleet',
            'name' => 'Fleet Management',
            'icon' => 'menu-icon icon-base ti tabler-chart-bar',
            'slug' => 'dispatcher.fleet.index'
        ],
        (object)[
            'url' => 'dispatcher/driver/list',
            'name' => 'Driver Management',
            'icon' => 'menu-icon icon-base ti tabler-users',
            'slug' => 'dispatcher.driver'
        ],
        (object)[
            'url' => 'dispatcher/client',
            'name' => 'Client Profiles',
            'icon' => 'menu-icon icon-base ti tabler-user-square',
            'slug' => 'dispatcher.client.index'
        ],
        (object)[
            'url' => 'dispatcher/compliance',
            'name' => 'Compliance Center',
            'icon' => 'menu-icon icon-base ti tabler-shield-check',
            'slug' => 'dispatcher.compliance'
        ],
        (object)[
            'url' => 'dispatcher/subscription',
            'name' => 'My Subscription',
            'icon' => 'menu-icon icon-base ti tabler-credit-card',
            'slug' => 'dispatcher.subscription'
        ],
        (object)[
            'url' => 'dispatcher/settings',
            'name' => 'Settings & Reports',
            'icon' => 'menu-icon icon-base ti tabler-settings',
            'slug' => 'dispatcher.settings'
        ],
    ];
} else {
    $menuItems = isset($menuData[0]->menu) ? $menuData[0]->menu : [];
}
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
