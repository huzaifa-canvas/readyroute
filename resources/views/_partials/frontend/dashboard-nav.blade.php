<!-- Dashboard Sub-Menu (Horizontal) -->
<div class="dashboard-nav-container">
    <div class="dashboard-nav-content">
        <a href="{{ route('frontend.profile') }}" class="dash-nav-item {{ request()->routeIs('frontend.profile') ? 'active' : '' }}">Profile Settings</a>
        <a href="{{ route('frontend.orders') }}" class="dash-nav-item {{ request()->routeIs('frontend.orders*') ? 'active' : '' }}">Order History</a>
        <a href="{{ route('frontend.checkout') }}" class="dash-nav-item {{ request()->routeIs('frontend.checkout') ? 'active' : '' }}">Billing & Payment</a>
        <form method="POST" action="{{ route('logout') }}" style="margin-left: auto;">
            @csrf
            <button type="submit" class="dash-nav-item" style="background: none; border: none; cursor: pointer; color: #ff3d00; font-family: inherit; font-size: inherit; padding: inherit;">Logout</button>
        </form>
    </div>
</div>
