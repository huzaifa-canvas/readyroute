<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
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

        $plans = SubscriptionPlan::orderBy('id', 'asc')->get();

        $invoices = [];

        return view('content.admin.subscription.index', compact('plans', 'invoices'));
    }

    /**
     * Show Create Plan page
     */
    public function create()
    {
        return view('content.admin.subscription.form', [
            'plan' => new SubscriptionPlan(),
            'isEdit' => false
        ]);
    }

    /**
     * Store new Subscription Plan
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|string|max:255',
            'billing_period' => 'nullable|string|max:50',
            'description' => 'required|string|max:500',
        ]);

        SubscriptionPlan::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'price' => $request->price,
            'billing_period' => $request->billing_period ?? '',
            'description' => $request->description,
            'is_featured' => false,
        ]);

        return redirect()->route('admin.subscription')->with('success', 'Subscription Plan created successfully.');
    }

    /**
     * Show Edit Plan page
     */
    public function edit($id)
    {
        $plan = SubscriptionPlan::findOrFail($id);

        return view('content.admin.subscription.form', [
            'plan' => $plan,
            'isEdit' => true
        ]);
    }

    /**
     * Update existing Subscription Plan
     */
    public function update(Request $request, $id)
    {
        $plan = SubscriptionPlan::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|string|max:255',
            'billing_period' => 'nullable|string|max:50',
            'description' => 'required|string|max:500',
        ]);

        $plan->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'price' => $request->price,
            'billing_period' => $request->billing_period ?? '',
            'description' => $request->description,
        ]);

        return redirect()->route('admin.subscription')->with('success', "'{$plan->name}' updated successfully.");
    }

    /**
     * Delete Subscription Plan
     */
    public function destroy($id)
    {
        $plan = SubscriptionPlan::findOrFail($id);
        $plan->delete();

        return redirect()->route('admin.subscription')->with('success', 'Subscription Plan deleted successfully.');
    }
}
