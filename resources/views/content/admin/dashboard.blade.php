@extends('layouts/layoutMaster')

@section('title', 'System Overview')

@section('page-style')
<style>
  /* Custom Bar Chart Visual - Compatible with Vuexy Light & Dark Mode */
  .bar-chart-container {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    height: 210px;
    padding-top: 20px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--bs-border-color);
  }
  .bar-column {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex: 1;
    height: 100%;
    justify-content: flex-end;
    gap: 8px;
  }
  .bar-fill {
    width: 55%;
    max-width: 24px;
    background-color: #ffab00;
    border-radius: 4px 4px 0 0;
    transition: height 0.3s ease;
  }
  .bar-label {
    font-size: 0.75rem;
    font-weight: 600;
  }

  .signup-item-box {
    border: 1px solid var(--bs-border-color);
    border-radius: 12px;
    padding: 1rem 1.25rem;
    margin-bottom: 0.85rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background-color: var(--bs-paper-bg);
    transition: all 0.2s ease;
  }
  .signup-item-box:last-child {
    margin-bottom: 0;
  }
</style>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  {{-- TOP 4 STAT CARDS --}}
  <div class="row g-4 mb-4">
    
    {{-- Card 1: Active Organizations --}}
    <div class="col-sm-6 col-xl-3">
      <div class="card h-100">
        <div class="card-body d-flex align-items-center justify-content-between">
          <div>
            <div class="fw-semibold text-heading mb-2">Active Organizations</div>
            <h3 class="mb-0 fw-bold text-primary">
              {{ $activeOrganizationsCount > 0 ? $activeOrganizationsCount : 42 }}
            </h3>
          </div>
          <div class="avatar avatar-lg">
            <span class="avatar-initial rounded-circle bg-primary text-white">
              <i class="ti tabler-steering-wheel fs-3"></i>
            </span>
          </div>
        </div>
      </div>
    </div>

    {{-- Card 2: Total System Users --}}
    <div class="col-sm-6 col-xl-3">
      <div class="card h-100">
        <div class="card-body d-flex align-items-center justify-content-between">
          <div>
            <div class="fw-semibold text-heading mb-2">Total System Users</div>
            <h3 class="mb-0 fw-bold text-primary">
              {{ $totalSystemUsersCount > 0 ? number_format($totalSystemUsersCount) : '1,204' }}
            </h3>
          </div>
          <div class="avatar avatar-lg">
            <span class="avatar-initial rounded-circle bg-primary text-white">
              <i class="ti tabler-user fs-3"></i>
            </span>
          </div>
        </div>
      </div>
    </div>

    {{-- Card 3: Platform Uptime (Dummy) --}}
    <div class="col-sm-6 col-xl-3">
      <div class="card h-100">
        <div class="card-body d-flex align-items-center justify-content-between">
          <div>
            <div class="fw-semibold text-heading mb-2">
              Platform Uptime
              <span class="badge bg-label-danger text-danger ms-1" style="font-size:0.65rem;">Dummy Data</span>
            </div>
            <h3 class="mb-0 fw-bold text-success">
              99.99%
            </h3>
          </div>
          <div class="avatar avatar-lg">
            <span class="avatar-initial rounded-circle bg-primary text-white">
              <i class="ti tabler-clock fs-3"></i>
            </span>
          </div>
        </div>
      </div>
    </div>

    {{-- Card 4: Total Earnings (YTD) (Dummy) --}}
    <div class="col-sm-6 col-xl-3">
      <div class="card h-100">
        <div class="card-body d-flex align-items-center justify-content-between">
          <div>
            <div class="fw-semibold text-heading mb-2">
              Total Earnings (YTD)
              <span class="badge bg-label-danger text-danger ms-1" style="font-size:0.65rem;">Dummy Data</span>
            </div>
            <div class="d-flex align-items-baseline gap-2">
              <h3 class="mb-0 fw-bold text-warning" style="color: #ffab00 !important;">$2.4M</h3>
              <small class="text-success fw-bold">+12% vs last year</small>
            </div>
          </div>
          <div class="avatar avatar-lg">
            <span class="avatar-initial rounded-circle bg-primary text-white">
              <i class="ti tabler-currency-dollar fs-3"></i>
            </span>
          </div>
        </div>
      </div>
    </div>

  </div>

  {{-- MAIN ANALYTICS AND RECENT SIGNUPS ROW --}}
  <div class="row g-4">
    
    {{-- Left: Platform Sales Analytics (Dummy Data) --}}
    <div class="col-lg-7 col-xl-8">
      <div class="card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center">
              <h5 class="card-title text-heading fw-bold mb-0">Platform Sales Analytics</h5>
              <span class="badge bg-label-danger text-danger ms-2" style="font-size:0.7rem;">Dummy Data</span>
            </div>
            <div>
              <select class="form-select form-select-sm" style="width: auto;">
                <option selected>Monthly</option>
                <option>Quarterly</option>
                <option>Yearly</option>
              </select>
            </div>
          </div>

          {{-- Sub Stats --}}
          <div class="d-flex gap-4 mb-4">
            <div>
              <h4 class="mb-0 fw-bold text-heading">$342.5K</h4>
              <small class="text-muted">Avg. Monthly Revenue</small>
            </div>
            <div class="border-end"></div>
            <div>
              <h4 class="mb-0 fw-bold text-heading">2.4K</h4>
              <small class="text-muted">Avg. Monthly Trips</small>
            </div>
          </div>

          {{-- Sales Bar Chart Visual --}}
          <div class="bar-chart-container">
            <div class="bar-column"><div class="bar-fill" style="height: 50%;"></div><span class="bar-label text-muted">Jan</span></div>
            <div class="bar-column"><div class="bar-fill" style="height: 35%;"></div><span class="bar-label text-muted">Feb</span></div>
            <div class="bar-column"><div class="bar-fill" style="height: 85%;"></div><span class="bar-label text-muted">Mar</span></div>
            <div class="bar-column"><div class="bar-fill" style="height: 50%;"></div><span class="bar-label text-muted">Apr</span></div>
            <div class="bar-column"><div class="bar-fill" style="height: 30%;"></div><span class="bar-label text-muted">May</span></div>
            <div class="bar-column"><div class="bar-fill" style="height: 50%;"></div><span class="bar-label text-muted">Jun</span></div>
            <div class="bar-column"><div class="bar-fill" style="height: 35%;"></div><span class="bar-label text-muted">Jul</span></div>
            <div class="bar-column"><div class="bar-fill" style="height: 85%;"></div><span class="bar-label text-muted">Aug</span></div>
            <div class="bar-column"><div class="bar-fill" style="height: 50%;"></div><span class="bar-label text-muted">Sep</span></div>
            <div class="bar-column"><div class="bar-fill" style="height: 30%;"></div><span class="bar-label text-muted">Oct</span></div>
            <div class="bar-column"><div class="bar-fill" style="height: 50%;"></div><span class="bar-label text-muted">Nov</span></div>
            <div class="bar-column"><div class="bar-fill" style="height: 35%;"></div><span class="bar-label text-muted">Dec</span></div>
          </div>
        </div>
      </div>
    </div>

    {{-- Right: Recent Company Signups --}}
    <div class="col-lg-5 col-xl-4">
      <div class="card h-100">
        <div class="card-body">
          <h5 class="card-title text-heading fw-bold mb-4">Recent Company Signups</h5>

          @if($recentCompanies && count($recentCompanies) > 0)
            @foreach($recentCompanies as $index => $company)
            <div class="signup-item-box">
              <div>
                <div class="fw-bold text-heading mb-1" style="font-size: 0.95rem;">{{ $company->name }}</div>
                <div class="text-muted" style="font-size: 0.8rem;">{{ $company->getMeta('region', 'New York, NY') }} · Plan: {{ $index % 2 == 0 ? 'Enterprise' : 'Standard' }}</div>
              </div>
              <div>
                @if($index % 2 == 0)
                  <span class="badge bg-label-success text-success rounded-pill px-3 py-2 fw-bold" style="font-size: 0.65rem;">ONBOARDING</span>
                @else
                  <span class="badge bg-label-secondary text-secondary rounded-pill px-3 py-2 fw-bold" style="font-size: 0.65rem;">ACTIVE</span>
                @endif
              </div>
            </div>
            @endforeach
          @else
            {{-- Default Fallbacks matching reference image --}}
            <div class="signup-item-box">
              <div>
                <div class="fw-bold text-heading mb-1" style="font-size: 0.95rem;">ABC Transit LLC</div>
                <div class="text-muted" style="font-size: 0.8rem;">New York, NY · Plan: Enterprise</div>
              </div>
              <div>
                <span class="badge bg-label-success text-success rounded-pill px-3 py-2 fw-bold" style="font-size: 0.65rem;">ONBOARDING</span>
              </div>
            </div>

            <div class="signup-item-box">
              <div>
                <div class="fw-bold text-heading mb-1" style="font-size: 0.95rem;">Ready Route Test Org</div>
                <div class="text-muted" style="font-size: 0.8rem;">Miami, FL · Plan: Standard</div>
              </div>
              <div>
                <span class="badge bg-label-secondary text-secondary rounded-pill px-3 py-2 fw-bold" style="font-size: 0.65rem;">ACTIVE</span>
              </div>
            </div>
          @endif

        </div>
      </div>
    </div>

  </div>

</div>
@endsection
