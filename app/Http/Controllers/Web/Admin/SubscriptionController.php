<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\StripeService;
use App\Support\PlanFeatures;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
    public function __construct(private readonly StripeService $stripe)
    {
    }

    /**
     * Show subscription plans list and recent invoices
     */
    public function index()
    {
        // Seed default 3 plans if table is empty
        if (SubscriptionPlan::count() === 0) {
            SubscriptionPlan::create([
                'name' => 'Basic Tier',
                'slug' => 'basic-tier',
                'price' => '$299',
                'billing_period' => '/mo',
                'description' => 'Up to 10 vehicles. Core dispatching features.',
                'is_featured' => false,
            ]);

            SubscriptionPlan::create([
                'name' => 'Professional Tier',
                'slug' => 'professional-tier',
                'price' => '$599',
                'billing_period' => '/mo',
                'description' => 'Up to 50 vehicles. Advanced reporting, API.',
                'is_featured' => false,
            ]);

            SubscriptionPlan::create([
                'name' => 'Enterprise Tier',
                'slug' => 'enterprise-tier',
                'price' => 'Custom',
                'billing_period' => '',
                'description' => 'Unlimited vehicles. Custom Integrations.',
                'is_featured' => false,
            ]);
        }

        $plans = SubscriptionPlan::withCount('companies')->orderBy('id', 'asc')->get();

        $invoices = $this->recentInvoices();

        // Companies that were assigned a tier by hand have no Stripe invoice
        // to show, which is why the list can legitimately be empty.
        $assignedOnly = User::query()
            ->companies()
            ->whereNotNull('subscription_plan_id')
            ->whereNull('stripe_subscription_id')
            ->count();

        $stripeReady = $this->stripe->isConfigured();

        return view('content.admin.subscription.index', compact(
            'plans', 'invoices', 'assignedOnly', 'stripeReady'
        ));
    }



    /**
     * The platform's own income: what our companies paid for their
     * subscriptions.
     *
     * Read from our table, not from Stripe's invoice list. The Stripe account
     * is shared with other products, so asking Stripe for "recent invoices"
     * returned other people's money as platform revenue.
     *
     * @return \Illuminate\Support\Collection<int, SubscriptionInvoice>
     */
    private function recentInvoices(int $limit = 15)
    {
        return SubscriptionInvoice::with(['company', 'plan'])
            ->orderByDesc('issued_at')
            ->limit($limit)
            ->get();
    }

    /**
     * What the plan form posts. Limits are nullable because null means
     * unlimited, which is a real answer rather than a missing one.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name'            => 'required|string|max:255',
            'price'           => 'required|string|max:255',
            'price_amount'    => 'nullable|numeric|min:0|max:999999.99',
            'billing_interval' => 'required|in:month,year,week,day',
            'description'     => 'required|string|max:500',
            'vehicle_limit'   => 'nullable|integer|min:1|max:100000',
            'driver_limit'    => 'nullable|integer|min:1|max:100000',
            'trip_limit'      => 'nullable|integer|min:1|max:1000000',
            'features'        => 'nullable|array',
            'features.*'      => 'string',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, Request $request): array
    {
        return [
            'name'            => $data['name'],
            'price'           => $data['price'],
            'price_amount'    => $data['price_amount'] ?? null,
            'billing_interval' => $data['billing_interval'],
            // Kept in step with the interval so nothing reads a stale suffix.
            'billing_period'   => SubscriptionPlan::intervals()[$data['billing_interval']]['suffix'],
            'description'     => $data['description'],
            'vehicle_limit'   => $data['vehicle_limit'] ?? null,
            'driver_limit'    => $data['driver_limit'] ?? null,
            'trip_limit'      => $data['trip_limit'] ?? null,
            // clean() drops anything unknown and forces the core features
            // back in, so a plan can never be saved unable to run a trip.
            'features'        => PlanFeatures::clean($request->input('features', [])),
            'is_featured'     => $request->boolean('is_featured'),
            'is_active'       => $request->boolean('is_active'),
        ];
    }


    /**
     * Create or update the plan in Stripe from the secret key, so the admin
     * never copies an id out of the dashboard.
     *
     * A failure is reported but does not undo the save: the plan is still a
     * valid tier the admin can assign, it just cannot be bought until Stripe
     * catches up.
     */
    private function syncToStripe(SubscriptionPlan $plan): string
    {
        if (! $this->stripe->isConfigured()) {
            return ' Stripe is not configured, so this tier cannot be bought yet.';
        }

        if ($plan->price_amount === null) {
            return ' No amount was set, so this stays a "contact us" tier.';
        }

        try {
            $priceId = $this->stripe->syncPlan($plan->fresh());

            return $priceId
                ? ' Synced to Stripe and ready to sell.'
                : ' Stripe did not return a price for it.';
        } catch (\Throwable $e) {
            report($e);

            return ' It could not be synced to Stripe yet — check the keys and save again.';
        }
    }

    /**
     * Show Create Plan page
     */
    public function create()
    {
        return view('content.admin.subscription.form', [
            'plan'   => new SubscriptionPlan(['features' => PlanFeatures::defaults(), 'is_active' => true]),
            'groups' => PlanFeatures::groups(),
            'isEdit' => false,
        ]);
    }

    /**
     * Store new Subscription Plan
     */
    public function store(Request $request)
    {
        $data = $this->validated($request);

        $plan = SubscriptionPlan::create($this->attributes($data, $request) + [
            'slug' => Str::slug($request->name),
        ]);

        $note = $this->syncToStripe($plan);

        return redirect()->route('admin.subscription')
            ->with('success', 'Subscription Plan created successfully.' . $note);
    }

    /**
     * Show Edit Plan page
     */
    public function edit($id)
    {
        $plan = SubscriptionPlan::findOrFail($id);

        return view('content.admin.subscription.form', [
            'plan'   => $plan,
            'groups' => PlanFeatures::groups(),
            'isEdit' => true,
        ]);
    }

    /**
     * Update existing Subscription Plan
     */
    public function update(Request $request, $id)
    {
        $plan = SubscriptionPlan::findOrFail($id);

        $data = $this->validated($request);

        $plan->update($this->attributes($data, $request));

        $note = $this->syncToStripe($plan);

        return redirect()->route('admin.subscription')
            ->with('success', "'{$plan->name}' updated successfully." . $note);
    }

    /**
     * Delete Subscription Plan
     */
    public function destroy($id)
    {
        $plan = SubscriptionPlan::withCount('companies')->findOrFail($id);

        if ($plan->companies_count > 0) {
            return back()->with(
                'error',
                $plan->companies_count . ' company/companies are on this plan. Move them first, '
                . 'or deactivate the plan instead of deleting it.'
            );
        }

        $plan->delete();

        return redirect()->route('admin.subscription')->with('success', 'Subscription Plan deleted successfully.');
    }
}
