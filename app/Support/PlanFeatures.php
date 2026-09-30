<?php

namespace App\Support;

/**
 * What a subscription tier can unlock.
 *
 * Separate from Permissions on purpose. A permission answers "is this person
 * allowed?", a feature answers "has this company paid for it?" — and both have
 * to pass. A Head Dispatcher on the Basic tier still cannot open System Users
 * if the admin did not include it in that tier.
 *
 * The admin edits the per-plan set from the plan form, so adding a capability
 * to the product means adding its key here and nowhere else.
 */
class PlanFeatures
{
    /**
     * Everything a tier can include, grouped for the plan editor.
     *
     * @return array<string, array<string, array{label: string, help: string}>>
     */
    public static function groups(): array
    {
        return [
            'Core dispatching' => [
                'trips'     => ['label' => 'Trips & scheduling',   'help' => 'Create, assign and track trips. Every plan needs this.'],
                'drivers'   => ['label' => 'Driver management',    'help' => 'Add drivers and hand them the mobile app.'],
                'clients'   => ['label' => 'Client profiles',      'help' => 'Client records, requirements and dated notes.'],
                'fleet'     => ['label' => 'Fleet management',     'help' => 'Vehicles, assignment and maintenance state.'],
            ],
            'Operations' => [
                'live_map'      => ['label' => 'Live operations map', 'help' => 'Drivers and vehicles on a live map.'],
                'auto_dispatch' => ['label' => 'Smart auto-dispatch', 'help' => 'Suggest assignments for unassigned trips.'],
                'incidents'     => ['label' => 'Incidents & SOS',     'help' => 'The driver panic button and the incident queue.'],
                'messaging'     => ['label' => 'Driver messaging',    'help' => 'Two-way chat with drivers.'],
                'compliance'    => ['label' => 'Compliance centre',   'help' => 'Licence and medical card expiry tracking.'],
                'tracking_link' => ['label' => 'Passenger tracking',  'help' => 'The SMS tracking link passengers open.'],
            ],
            'Business' => [
                'reports' => ['label' => 'Reports & analytics', 'help' => 'Operational reporting and CSV export.'],
                'billing' => ['label' => 'Billing & claims',    'help' => 'Invoice payers and track claims.'],
            ],
            'Team' => [
                'system_users' => ['label' => 'Team members',   'help' => 'Add panel users beyond the company account.'],
                'custom_roles' => ['label' => 'Custom roles',   'help' => 'Define permission sets of your own, beyond the platform roles.'],
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return array_keys(array_merge(...array_values(static::groups())));
    }

    public static function exists(string $feature): bool
    {
        return in_array($feature, static::all(), true);
    }

    public static function label(string $feature): string
    {
        foreach (static::groups() as $features) {
            if (isset($features[$feature])) {
                return $features[$feature]['label'];
            }
        }

        return $feature;
    }

    /**
     * Features no plan can sell without — a panel that cannot run a trip is
     * not a product. They stay ticked and are re-added even if a form omits
     * them.
     *
     * @return array<int, string>
     */
    public static function mandatory(): array
    {
        return ['trips', 'drivers', 'clients', 'fleet'];
    }

    /**
     * Drop anything unknown and force the mandatory set back in.
     *
     * @param  array<int, mixed>  $features
     * @return array<int, string>
     */
    public static function clean(array $features): array
    {
        $clean = array_filter(
            array_map(fn ($feature) => is_string($feature) ? $feature : null, $features),
            fn ($feature) => $feature !== null && static::exists($feature)
        );

        $clean = array_values(array_unique(array_merge($clean, static::mandatory())));

        sort($clean);

        return $clean;
    }

    /**
     * What a plan gets when the admin has never edited it. Generous rather
     * than restrictive: an existing tenant must not lose a screen because a
     * column was added underneath them.
     *
     * @return array<int, string>
     */
    public static function defaults(): array
    {
        return static::all();
    }
}
