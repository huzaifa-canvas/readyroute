<?php

namespace App\Http\Controllers\Api\Dispatcher;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * The settings screen, and the profile it opens with.
 *
 * The notification and display switches are per-user preferences kept in user
 * meta, the same store the web panel uses, so turning something off on the
 * phone is off everywhere.
 */
class SettingsController extends BaseDispatcherController
{
    /**
     * Every switch the screen draws, with its default.
     *
     * Defaults are stated here rather than assumed false: a brand new account
     * should arrive with push and trip alerts already on, which is what
     * someone installing a dispatch app expects.
     */
    private const TOGGLES = [
        'notify_push'            => ['label' => 'Push Notifications',      'group' => 'notifications', 'default' => true],
        'notify_sms'             => ['label' => 'SMS Alerts',              'group' => 'notifications', 'default' => false],
        'notify_email_digest'    => ['label' => 'Email Digest',            'group' => 'notifications', 'default' => false],
        'notify_trip_assignment' => ['label' => 'Trip Assignment Alerts',  'group' => 'notifications', 'default' => true],
        'notify_driver_status'   => ['label' => 'Driver Status Updates',   'group' => 'notifications', 'default' => true],

        'pref_dark_mode'      => ['label' => 'Dark Mode',           'group' => 'preferences', 'default' => false],
        'pref_compact_view'   => ['label' => 'Compact View',        'group' => 'preferences', 'default' => false],
        'pref_default_to_map' => ['label' => 'Default to Map View', 'group' => 'preferences', 'default' => false],
    ];

    public function show(): JsonResponse
    {
        $user = $this->user();

        $groups = [];

        foreach (self::TOGGLES as $key => $meta) {
            $groups[$meta['group']][] = [
                'key'   => $key,
                'label' => $meta['label'],
                'value' => $this->toggle($key),
            ];
        }

        return $this->ok([
            'profile' => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'phone'      => $user->phone_number,
                'avatar_url' => $user->avatar_url,
                // "Company Dispatcher · ABC Transit LLC" under the name.
                'role_label'   => $user->isCompanyOwner() ? 'Company Dispatcher' : 'Dispatcher',
                'company_name' => $user->getMeta('company_name') ?: $user->name,
                'is_owner'     => $user->isCompanyOwner(),
            ],

            'notifications' => $groups['notifications'] ?? [],
            'preferences'   => $groups['preferences'] ?? [],

            // The account rows at the bottom. Which ones appear depends on
            // who is signed in: only the company account can see billing.
            'account' => array_values(array_filter([
                ['key' => 'company',      'label' => 'Company Profile', 'available' => $user->isCompanyOwner()],
                ['key' => 'integrations', 'label' => 'Integrations',    'available' => $user->isCompanyOwner()],
                ['key' => 'security',     'label' => 'Security',        'available' => true],
                ['key' => 'earning',      'label' => 'Earning',         'available' => $user->isCompanyOwner()],
                ['key' => 'support',      'label' => 'Help & Support',  'available' => true],
            ], fn ($row) => $row['available'])),

            'two_factor_enabled' => (bool) $user->getMeta('two_factor_enabled', false)
                || $user->two_factor_confirmed_at !== null,
        ]);
    }

    /**
     * Flip one switch, or several at once.
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'settings'   => ['required', 'array'],
            'settings.*' => ['boolean'],
        ]);

        $unknown = array_diff(array_keys($request->input('settings')), array_keys(self::TOGGLES));

        if ($unknown !== []) {
            return $this->fail('Unknown setting: ' . implode(', ', $unknown), 422);
        }

        foreach ($request->input('settings') as $key => $value) {
            $this->user()->setMeta($key, $value ? '1' : '0');
        }

        return $this->show();
    }

    /**
     * The company's own details. Only the account that owns the company may
     * change these, exactly as on the web panel.
     */
    public function company(): JsonResponse
    {
        $user = $this->user();

        return $this->ok([
            'company_name'  => $user->getMeta('company_name') ?: $user->name,
            'contact_email' => $user->getMeta('contact_email') ?: $user->email,
            'billing' => [
                'default_tax_rate'   => $user->getMeta('default_tax_rate', 0),
                'auto_send_invoices' => (bool) $user->getMeta('auto_send_invoices', false),
            ],
            'reports' => [
                'weekly_summary'     => (bool) $user->getMeta('report_weekly_summary', false),
                'monthly_revenue'    => (bool) $user->getMeta('report_monthly_revenue', false),
                'driver_performance' => (bool) $user->getMeta('report_driver_performance', false),
            ],
            'permissions' => [
                'allow_notes_edit'   => (bool) $user->getMeta('perm_allow_notes_edit', false),
                'allow_billing_view' => (bool) $user->getMeta('perm_allow_billing_view', false),
                'require_2fa'        => (bool) $user->getMeta('perm_require_2fa', false),
            ],
        ]);
    }

    public function updateCompany(Request $request): JsonResponse
    {
        $this->requireOwner();

        $request->validate([
            'company_name'  => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email', 'max:255'],
            'default_tax_rate'   => ['nullable', 'numeric', 'min:0', 'max:100'],
            'auto_send_invoices' => ['nullable', 'boolean'],
            'report_weekly_summary'     => ['nullable', 'boolean'],
            'report_monthly_revenue'    => ['nullable', 'boolean'],
            'report_driver_performance' => ['nullable', 'boolean'],
            'perm_allow_notes_edit'   => ['nullable', 'boolean'],
            'perm_allow_billing_view' => ['nullable', 'boolean'],
            'perm_require_2fa'        => ['nullable', 'boolean'],
        ]);

        $user = $this->user();

        $user->setMeta('company_name', $request->input('company_name'));
        $user->setMeta('contact_email', $request->input('contact_email'));

        foreach ([
            'default_tax_rate',
            'auto_send_invoices',
            'report_weekly_summary',
            'report_monthly_revenue',
            'report_driver_performance',
            'perm_allow_notes_edit',
            'perm_allow_billing_view',
            'perm_require_2fa',
        ] as $key) {
            if ($request->has($key)) {
                $user->setMeta($key, $request->input($key));
            }
        }

        return $this->company();
    }

    // ── Profile ──────────────────────────────────────────────────────

    public function profile(): JsonResponse
    {
        $user = $this->user();

        return $this->ok([
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'phone'      => $user->phone_number,
            'avatar_url' => $user->avatar_url,
            'role'       => $user->role,
            'is_owner'   => $user->isCompanyOwner(),
            'two_factor_enabled' => (bool) $user->getMeta('two_factor_enabled', false)
                || $user->two_factor_confirmed_at !== null,
            'subscription' => [
                'status' => $user->subscriptionStatusLabel(),
                'plan'   => $user->companyId() === $user->id
                    ? $user->subscriptionPlan?->name
                    : null,
            ],
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $this->user();

        $request->validate([
            'name'   => ['required', 'string', 'max:255'],
            'email'  => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone'  => ['nullable', 'string', 'max:20'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
        ]);

        $user->name  = $request->input('name');
        $user->email = $request->input('email');

        if ($request->has('phone')) {
            $user->phone_number = $request->input('phone');
        }

        if ($request->hasFile('avatar')) {
            // Stored on the public disk and served through storage/, the same
            // place the web panel reads avatars from.
            if ($user->profile_image) {
                Storage::disk('public')->delete($user->profile_image);
            }

            $user->profile_image = $request->file('avatar')->store('profile_images', 'public');
        }

        $user->save();

        return $this->profile();
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($request->input('current_password'), $this->user()->password)) {
            return $this->fail('Current password does not match our records.', 422, [
                'current_password' => ['Current password does not match our records.'],
            ]);
        }

        $this->user()->forceFill(['password' => Hash::make($request->input('password'))])->save();

        return $this->ok(null, 'Password updated.');
    }

    public function toggleTwoFactor(): JsonResponse
    {
        $user = $this->user();

        $enabled = (bool) $user->getMeta('two_factor_enabled', false)
            || $user->two_factor_confirmed_at !== null;

        if ($enabled) {
            $user->setMeta('two_factor_enabled', false);
            $user->forceFill([
                'two_factor_confirmed_at' => null,
                'two_factor_secret'       => null,
            ])->save();

            return $this->ok(['two_factor_enabled' => false], 'Two-factor authentication disabled.');
        }

        $user->setMeta('two_factor_enabled', true);
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        return $this->ok(['two_factor_enabled' => true], 'Two-factor authentication enabled.');
    }

    /**
     * Help & Support: who to contact, and what the app should say about itself.
     */
    public function support(): JsonResponse
    {
        $user = $this->user();

        return $this->ok([
            'company_name'       => $user->getMeta('company_name') ?: $user->name,
            'contact_email'      => $user->getMeta('contact_email') ?: $user->email,
            'dispatch_phone'     => $user->getMeta('dispatch_phone') ?: $user->phone_number,
            'privacy_policy_url' => url('/privacy-policy'),
            'app_name'           => config('app.name'),
        ]);
    }

    private function toggle(string $key): bool
    {
        $stored = $this->user()->getMeta($key);

        if ($stored === null || $stored === '') {
            return self::TOGGLES[$key]['default'];
        }

        return (bool) $stored;
    }
}
