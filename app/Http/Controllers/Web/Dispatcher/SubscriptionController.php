<?php

namespace App\Http\Controllers\Web\Dispatcher;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * The company's own subscription.
 *
 * Plan changes are recorded here but no money moves: there is no payment
 * provider connected yet, so switching tiers takes effect immediately and the
 * screen says so rather than pretending a card was charged.
 */
class SubscriptionController extends Controller
{
    public function index()
    {
        $company = User::findOrFail(auth()->user()->companyId());
        $company->load('subscriptionPlan');

        $plans = SubscriptionPlan::orderBy('price_amount')->get();
        $usage = $company->planUsage();

        // Anything already over its cap is called out, because the usage bar
        // alone tops out at 100% and hides how far over it went.
        $exceeded = collect($usage)
            ->filter(fn ($row) => $row['limit'] !== null && $row['used'] > $row['limit'])
            ->keys()
            ->all();

        return view('content.dispatcher.subscription.index', compact('company', 'plans', 'usage', 'exceeded'));
    }

    public function change(Request $request)
    {
        abort_unless(
            auth()->user()->isCompanyOwner() || auth()->user()->isAdmin(),
            403,
            'Only the company account can change the subscription.'
        );

        $request->validate([
            'subscription_plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
        ]);

        $company = User::findOrFail(auth()->user()->companyId());
        $plan    = SubscriptionPlan::findOrFail($request->integer('subscription_plan_id'));

        if ($company->subscription_plan_id === $plan->id) {
            return back()->with('error', 'You are already on the ' . $plan->name . '.');
        }

        $company->forceFill([
            'subscription_plan_id' => $plan->id,
            'subscribed_at'        => now(),
            // The period always runs to the start of the next month, which is
            // what the billing date on the screen counts down to.
            'renews_at'            => now()->addMonth()->startOfMonth()->toDateString(),
        ])->save();

        return back()->with(
            'success',
            'Switched to the ' . $plan->name . '. No payment has been taken — billing is set up separately.'
        );
    }
}
