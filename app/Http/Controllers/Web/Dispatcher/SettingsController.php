<?php

namespace App\Http\Controllers\Web\Dispatcher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $activeTab = $request->query('tab', 'organization');

        return view('content.dispatcher.settings.index', compact('user', 'activeTab'));
    }

    public function update(Request $request)
    {
        $user = auth()->user();
        $tab = $request->input('tab', 'organization');

        if ($tab === 'organization') {
            $request->validate([
                'company_name' => 'required|string|max:255',
                'contact_email' => 'required|email|max:255',
            ]);

            $user->setMeta('company_name', $request->company_name);
            $user->setMeta('contact_email', $request->contact_email);
            // Optionally update user name/email
            $user->name = $request->company_name;
            $user->save();

            return redirect()->route('dispatcher.settings', ['tab' => 'organization'])
                ->with('success', 'Organization profile updated successfully.');
        }

        if ($tab === 'billing') {
            $user->setMeta('default_tax_rate', $request->input('default_tax_rate', 0));
            $user->setMeta('auto_send_invoices', $request->has('auto_send_invoices'));

            return redirect()->route('dispatcher.settings', ['tab' => 'billing'])
                ->with('success', 'Billing settings saved successfully.');
        }

        if ($tab === 'reports') {
            $user->setMeta('report_weekly_summary', $request->has('report_weekly_summary'));
            $user->setMeta('report_monthly_revenue', $request->has('report_monthly_revenue'));
            $user->setMeta('report_driver_performance', $request->has('report_driver_performance'));

            return redirect()->route('dispatcher.settings', ['tab' => 'reports'])
                ->with('success', 'Automated reporting preferences updated.');
        }

        if ($tab === 'permissions') {
            $user->setMeta('perm_allow_notes_edit', $request->has('perm_allow_notes_edit'));
            $user->setMeta('perm_allow_billing_view', $request->has('perm_allow_billing_view'));
            $user->setMeta('perm_require_2fa', $request->has('perm_require_2fa'));

            return redirect()->route('dispatcher.settings', ['tab' => 'permissions'])
                ->with('success', 'Global permissions saved successfully.');
        }

        return redirect()->back();
    }
}
