<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\TripStatus;
use App\Http\Controllers\Controller;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use App\Models\Trip;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Support\Carbon;

/**
 * The platform owner's overview.
 *
 * Every figure here is read from the database. Where a number cannot honestly
 * be produced — uptime, for instance, which nothing in this app measures — the
 * tile is simply not shown rather than filled with something invented.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly StripeService $stripe)
    {
    }

    public function index()
    {
        $companies = User::query()->companies()->with('subscriptionPlan')->get();

        $active = $companies->where('subscription_status', 'active');

        // Monthly recurring revenue: every paying company's plan, normalised
        // to a month so tiers on different billing periods are comparable.
        $mrr = $active->sum(function (User $company) {
            $plan = $company->subscriptionPlan;

            if (! $plan || $plan->price_amount === null) {
                return 0.0;
            }

            return (float) $plan->price_amount / match ($plan->billing_interval) {
                'year'  => 12,
                'week'  => 0.25,
                'day'   => 1 / 30,
                default => 1,
            };
        });

        $thisMonth = [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()];
        $lastMonth = [
            now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
            now()->subMonthNoOverflow()->endOfMonth()->toDateString(),
        ];

        $tripsThisMonth = Trip::whereBetween('pickup_date', $thisMonth)->count();
        $tripsLastMonth = Trip::whereBetween('pickup_date', $lastMonth)->count();

        $stats = [
            'organisations'   => $companies->count(),
            'active_orgs'     => $active->count(),
            'users'           => User::count(),
            'drivers'         => User::where('role', 'driver')->count(),
            'mrr'             => round($mrr, 2),
            'trips_month'     => $tripsThisMonth,
            'trips_change'    => $this->percentChange($tripsLastMonth, $tripsThisMonth),
            'trips_total'     => Trip::count(),
            'completed_month' => Trip::whereBetween('pickup_date', $thisMonth)
                ->where('status', TripStatus::Completed->value)
                ->count(),
            'unsubscribed'    => $companies->where('subscription_status', '!=', 'active')->count(),
        ];

        // Twelve months of platform-wide trip volume, with empty months kept
        // so the shape of the year is honest.
        $byMonth = collect();

        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonthsNoOverflow($i);

            $byMonth->push([
                'label' => $month->format('M'),
                'date'  => $month->copy()->startOfMonth(),
                'count' => Trip::whereBetween('pickup_date', [
                    $month->copy()->startOfMonth()->toDateString(),
                    $month->copy()->endOfMonth()->toDateString(),
                ])->count(),
            ]);
        }

        // How the tenants are spread across the tiers.
        $planBreakdown = SubscriptionPlan::orderByRaw('price_amount IS NULL')
            ->orderBy('price_amount')
            ->get()
            ->map(fn (SubscriptionPlan $plan) => (object) [
                'plan'  => $plan,
                'count' => $companies->where('subscription_plan_id', $plan->id)->count(),
            ]);

        $revenue = $this->revenueByMonth();

        $stats['revenue_total'] = round($revenue->sum('amount'), 2);

        $recentCompanies = $companies->sortByDesc('created_at')->take(5)->values();

        return view('content.admin.dashboard', compact(
            'stats', 'byMonth', 'planBreakdown', 'recentCompanies', 'revenue'
        ));
    }


    /**
     * What was actually collected each month, from our own invoice records.
     *
     * MRR is what the platform expects to bill; this is what arrived. They
     * differ whenever a card fails or a company signs up mid-month, which is
     * exactly why both are worth showing.
     *
     * @return \Illuminate\Support\Collection<int, array{label: string, amount: float}>
     */
    private function revenueByMonth(int $months = 12)
    {
        $from = now()->subMonthsNoOverflow($months - 1)->startOfMonth();

        $paid = SubscriptionInvoice::paid()
            ->where('issued_at', '>=', $from)
            ->get()
            ->groupBy(fn (SubscriptionInvoice $invoice) => $invoice->issued_at->format('Y-m'));

        $buckets = collect();

        for ($i = $months - 1; $i >= 0; $i--) {
            $month = now()->subMonthsNoOverflow($i);
            $key   = $month->format('Y-m');

            $buckets->push([
                'label'  => $month->format('M'),
                // get() rather than [] — a month with no payments is the
                // normal case, not an error.
                'amount' => round((float) ($paid->get($key)?->sum('amount') ?? 0), 2),
            ]);
        }

        return $buckets;
    }

    /**
     * Percentage change, or null when there is no baseline to compare against
     * — "+100%" from a month with no trips says nothing useful.
     */
    private function percentChange(int $previous, int $current): ?int
    {
        if ($previous === 0) {
            return null;
        }

        return (int) round(($current - $previous) / $previous * 100);
    }
}
