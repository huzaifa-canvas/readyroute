@extends('layouts/layoutMaster')

@section('title', 'Settings & Reports')

@section('page-style')
<style>
  .nav-pills-settings .nav-link {
    border: 1px solid var(--bs-border-color);
  }
  .nav-pills-settings .nav-link:not(.active) {
    background-color: var(--bs-paper-bg);
    color: var(--bs-body-color);
  }
  .settings-option-box {
    background-color: var(--bs-paper-bg);
    border: 1px solid var(--bs-border-color);
    border-radius: 12px;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
  }
  .settings-option-box:last-child {
    margin-bottom: 0;
  }
</style>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  {{-- Alert Messages --}}
  @if(session('success'))
    <div class="alert alert-success alert-dismissible mb-4" role="alert">
      <i class="ti tabler-circle-check me-2"></i>{{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  {{-- Header with Title & Navigation Tabs --}}
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
      <h4 class="fw-bold text-heading mb-0">
        @if($activeTab == 'reports') System Reports
        @elseif($activeTab == 'permissions') User Permissions
        @else Settings & Reports
        @endif
      </h4>
    </div>
    <div>
      <ul class="nav nav-pills nav-pills-settings gap-2">
        <li class="nav-item">
          <a class="nav-link {{ $activeTab == 'organization' ? 'active' : '' }}" href="{{ route('dispatcher.settings', ['tab' => 'organization']) }}">
            Organization Profile
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link {{ $activeTab == 'billing' ? 'active' : '' }}" href="{{ route('dispatcher.settings', ['tab' => 'billing']) }}">
            Billing & Invoicing
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link {{ $activeTab == 'reports' ? 'active' : '' }}" href="{{ route('dispatcher.settings', ['tab' => 'reports']) }}">
            System Reports
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link {{ $activeTab == 'permissions' ? 'active' : '' }}" href="{{ route('dispatcher.settings', ['tab' => 'permissions']) }}">
            User Permissions
          </a>
        </li>
      </ul>
    </div>
  </div>

  {{-- TAB CONTENT CARDS --}}
  <div class="row">
    <div class="col-12">
      
      {{-- TAB 1: ORGANIZATION PROFILE --}}
      @if($activeTab == 'organization')
      <div class="card">
        <div class="card-body p-4 p-md-5">
          <h4 class="fw-bold text-heading mb-4">Organization Info</h4>

          <form action="{{ route('dispatcher.settings.update') }}" method="POST">
            @csrf
            <input type="hidden" name="tab" value="organization">

            <div class="row g-4">
              <div class="col-md-12">
                <label class="form-label fw-semibold" for="company_name">Company Name</label>
                <input
                  type="text"
                  id="company_name"
                  name="company_name"
                  class="form-control"
                  placeholder="Ready Route Transport"
                  value="{{ old('company_name', $user->getMeta('company_name', $user->name ?? 'Ready Route Transport')) }}"
                  required />
              </div>

              <div class="col-md-12">
                <label class="form-label fw-semibold" for="contact_email">Contact Email</label>
                <input
                  type="email"
                  id="contact_email"
                  name="contact_email"
                  class="form-control"
                  placeholder="admin@readyroute.com"
                  value="{{ old('contact_email', $user->getMeta('contact_email', $user->email ?? 'admin@readyroute.com')) }}"
                  required />
              </div>
            </div>

            <div class="mt-4 pt-2">
              <button type="submit" class="btn btn-primary px-4">
                Save Changes
              </button>
            </div>
          </form>
        </div>
      </div>
      @endif

      {{-- TAB 2: BILLING & INVOICING --}}
      @if($activeTab == 'billing')
      <div class="card">
        <div class="card-body p-4 p-md-5">
          <h4 class="fw-bold text-heading mb-4">Billing & Invoicing</h4>

          <form action="{{ route('dispatcher.settings.update') }}" method="POST">
            @csrf
            <input type="hidden" name="tab" value="billing">

            <div class="row g-4 mb-4">
              {{-- Default Tax Rate --}}
              <div class="col-md-12">
                <label class="form-label fw-semibold" for="default_tax_rate">Default Tax Rate (%)</label>
                <input
                  type="number"
                  step="0.01"
                  id="default_tax_rate"
                  name="default_tax_rate"
                  class="form-control"
                  placeholder="0"
                  value="{{ old('default_tax_rate', $user->getMeta('default_tax_rate', 1)) }}" />
              </div>
            </div>

            {{-- Auto-Send Invoices Toggle --}}
            <div class="settings-option-box mb-4">
              <div>
                <div class="fw-bold text-heading mb-1" style="font-size: 0.95rem;">Auto-Send Invoices</div>
                <small class="text-muted d-block">Automatically Email Clients Their Invoices Upon Trip Completion.</small>
              </div>
              <div class="form-check form-switch m-0">
                <input
                  class="form-check-input"
                  type="checkbox"
                  id="auto_send_invoices"
                  name="auto_send_invoices"
                  {{ $user->getMeta('auto_send_invoices', true) ? 'checked' : '' }} />
              </div>
            </div>

            <div class="mt-4 pt-2">
              <button type="submit" class="btn btn-primary px-4">
                Save Billing Settings
              </button>
            </div>
          </form>
        </div>
      </div>
      @endif

      {{-- TAB 3: SYSTEM REPORTS --}}
      @if($activeTab == 'reports')
      <div class="card">
        <div class="card-body p-4 p-md-5">
          <h4 class="fw-bold text-heading mb-4">Automated Reporting</h4>

          <form action="{{ route('dispatcher.settings.update') }}" method="POST">
            @csrf
            <input type="hidden" name="tab" value="reports">

            {{-- Option 1: Weekly Trip Summary --}}
            <div class="settings-option-box">
              <div>
                <div class="fw-bold text-heading mb-1" style="font-size: 0.95rem;">Weekly Trip Summary</div>
                <small class="text-muted d-block">Receive A CSV Dump Of All Completed Trips Every Sunday.</small>
              </div>
              <div class="form-check form-switch m-0">
                <input
                  class="form-check-input"
                  type="checkbox"
                  id="report_weekly_summary"
                  name="report_weekly_summary"
                  {{ $user->getMeta('report_weekly_summary', true) ? 'checked' : '' }} />
              </div>
            </div>

            {{-- Option 2: Monthly Revenue Report --}}
            <div class="settings-option-box">
              <div>
                <div class="fw-bold text-heading mb-1" style="font-size: 0.95rem;">Monthly Revenue Report</div>
                <small class="text-muted d-block">Financial Breakdown Of Claims And Broker Billing.</small>
              </div>
              <div class="form-check form-switch m-0">
                <input
                  class="form-check-input"
                  type="checkbox"
                  id="report_monthly_revenue"
                  name="report_monthly_revenue"
                  {{ $user->getMeta('report_monthly_revenue', true) ? 'checked' : '' }} />
              </div>
            </div>

            {{-- Option 3: Driver Performance Digest --}}
            <div class="settings-option-box mb-4">
              <div>
                <div class="fw-bold text-heading mb-1" style="font-size: 0.95rem;">Driver Performance Digest</div>
                <small class="text-muted d-block">On-Time Percentages And Cancellation Counts Per Driver.</small>
              </div>
              <div class="form-check form-switch m-0">
                <input
                  class="form-check-input"
                  type="checkbox"
                  id="report_driver_performance"
                  name="report_driver_performance"
                  {{ $user->getMeta('report_driver_performance', false) ? 'checked' : '' }} />
              </div>
            </div>

            <div class="mt-4 pt-2">
              <button type="submit" class="btn btn-primary px-4">
                Download Latest CSV Report
              </button>
            </div>
          </form>
        </div>
      </div>
      @endif

      {{-- TAB 4: USER PERMISSIONS --}}
      @if($activeTab == 'permissions')
      <div class="card">
        <div class="card-body p-4 p-md-5">
          <h4 class="fw-bold text-heading mb-1">Global Permissions</h4>
          <p class="text-muted mb-4 small">Configure Default Access Policies For All Users Within Your Organization.</p>

          <form action="{{ route('dispatcher.settings.update') }}" method="POST">
            @csrf
            <input type="hidden" name="tab" value="permissions">

            {{-- Option 1: Allow Drivers To Edit Trip Notes --}}
            <div class="d-flex align-items-center justify-content-between py-3 border-bottom">
              <span class="fw-semibold text-heading">Allow Drivers To Edit Trip Notes</span>
              <div class="form-check form-switch m-0">
                <input
                  class="form-check-input"
                  type="checkbox"
                  id="perm_allow_notes_edit"
                  name="perm_allow_notes_edit"
                  {{ $user->getMeta('perm_allow_notes_edit', false) ? 'checked' : '' }} />
              </div>
            </div>

            {{-- Option 2: Allow Dispatchers To View Billing / Invoices --}}
            <div class="d-flex align-items-center justify-content-between py-3 border-bottom">
              <span class="fw-semibold text-heading">Allow Dispatchers To View Billing / Invoices</span>
              <div class="form-check form-switch m-0">
                <input
                  class="form-check-input"
                  type="checkbox"
                  id="perm_allow_billing_view"
                  name="perm_allow_billing_view"
                  {{ $user->getMeta('perm_allow_billing_view', false) ? 'checked' : '' }} />
              </div>
            </div>

            {{-- Option 3: Require Two-Factor Auth (2FA) For All Users --}}
            <div class="d-flex align-items-center justify-content-between py-3 border-bottom mb-4">
              <span class="fw-semibold text-heading">Require Two-Factor Auth (2FA) For All Users</span>
              <div class="form-check form-switch m-0">
                <input
                  class="form-check-input"
                  type="checkbox"
                  id="perm_require_2fa"
                  name="perm_require_2fa"
                  {{ $user->getMeta('perm_require_2fa', true) ? 'checked' : '' }} />
              </div>
            </div>

            <div class="pt-2">
              <button type="submit" class="btn btn-primary px-4">
                Save Permissions
              </button>
            </div>
          </form>
        </div>
      </div>
      @endif

    </div>
  </div>

</div>
@endsection
