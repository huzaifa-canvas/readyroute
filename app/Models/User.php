<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'profile_image',
        'role',
        'role_id',
        'status',
        'subscription_plan_id',
        'subscription_status',
        'subscribed_at',
        'renews_at',
        'stripe_customer_id',
        'stripe_subscription_id',
        'trial_ends_at',
        'cancels_at',
        'suspended_at',
        'suspension_reason',
        'driver_code',
        'dispatcher_id',
        'phone_number',
        'device_token',
        'is_online',
        'last_seen_at',
        'last_lat',
        'last_lng',
        'last_location_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'device_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_online' => 'boolean',
            'last_seen_at' => 'datetime',
            'last_lat' => 'decimal:8',
            'last_lng' => 'decimal:8',
            'last_location_at' => 'datetime',
            'suspended_at' => 'datetime',
            'subscribed_at' => 'datetime',
            'renews_at' => 'date',
            'trial_ends_at' => 'datetime',
            'cancels_at' => 'datetime',
            'pending_plan_starts_at' => 'datetime',
        ];
    }

    /**
     * Give every driver a readable reference (#DRV-0047) the moment they are
     * created, so the sign-off screen always has something to print.
     */
    protected static function booted(): void
    {
        static::creating(function (self $user) {
            if ($user->role === 'driver' && empty($user->driver_code)) {
                $user->driver_code = static::nextDriverCode();
            }
        });
    }

    public static function nextDriverCode(): string
    {
        /*
         * Two things this has to get right, and used to get wrong.
         *
         * A soft-deleted driver still holds its code in the unique index, so
         * trashed rows have to be counted. Reading only the live ones meant
         * that deleting a driver broke the very next creation, permanently:
         * the query handed back the code below the deleted one and the insert
         * collided with a row nobody could see.
         *
         * And the newest row is not the highest code. Ordering by id assumes
         * codes were only ever handed out in insert order, which stops being
         * true the moment one is imported or set by hand.
         */
        $taken = static::withTrashed()
            ->whereNotNull('driver_code')
            ->pluck('driver_code');

        $highest = (int) $taken
            ->map(fn (string $code) => (int) preg_replace('/\D/', '', $code))
            ->max();

        $next  = $highest + 1;
        $codes = $taken->flip();

        // max + 1 is normally free. The loop only matters when a code was
        // written in some other shape, which would otherwise collide again.
        while ($codes->has($code = 'DRV-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT))) {
            $next++;
        }

        return $code;
    }

    /**
     * Role checks
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isDispatcher(): bool
    {
        return $this->role === 'dispatcher';
    }

    public function isDriver(): bool
    {
        return $this->role === 'driver';
    }

    /**
     * Where this user's web panel starts. Null for roles with no panel —
     * drivers use the mobile app only.
     */
    public function panelHome(): ?string
    {
        return match (true) {
            $this->isAdmin()      => '/admin',
            $this->isDispatcher() => '/dispatcher',
            default               => null,
        };
    }

    /**
     * Account status
     *
     * role says what kind of account this is; status says whether it may be
     * used. A suspended account keeps all its data and can be brought back,
     * which is what makes the admin's suspend action safe to use.
     */
    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isActive(): bool
    {
        return ! $this->isSuspended();
    }

    public function suspend(?string $reason = null): void
    {
        $this->forceFill([
            'status'            => 'suspended',
            'suspended_at'      => now(),
            'suspension_reason' => $reason,
        ])->save();

        // A suspended account must not keep working through a token issued
        // before the suspension.
        $this->tokens()->delete();
    }

    public function activate(): void
    {
        $this->forceFill([
            'status'            => 'active',
            'suspended_at'      => null,
            'suspension_reason' => null,
        ])->save();
    }

    /**
     * The trips that stand in the way of deactivating this driver.
     *
     * Deactivating is not the same as deleting: the account stays, so its
     * history stays with it. What must not stay is work. A driver who cannot
     * sign in but is still named on a run leaves that run in limbo — nobody is
     * driving it and nothing on the board says so.
     *
     * Everything still on their plate counts, a booking two weeks out as much
     * as a trip under way: both need a driver, and neither has one once this
     * account is switched off.
     */
    public function tripsBlockingDeactivation(): \Illuminate\Database\Eloquent\Builder
    {
        return Trip::query()->forDriver($this->id)->active();
    }

    /**
     * Whether this driver can be switched off right now.
     */
    public function canBeDeactivated(): bool
    {
        return $this->tripsBlockingDeactivation()->doesntExist();
    }

    public function statusLabel(): string
    {
        return $this->isSuspended() ? 'Suspended' : 'Active';
    }

    public function statusClass(): string
    {
        return $this->isSuspended() ? 'bg-label-warning' : 'bg-label-success';
    }

    /**
     * Permissions
     *
     * An admin is unconditionally allowed everything, and a dispatcher who
     * owns the company is too — the finer roles exist for the staff they add
     * beneath them, so a company owner without a role is not locked out of
     * their own panel.
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($this->isDispatcher() && ! $this->dispatcher_id) {
            return true;
        }

        return (bool) $this->accessRole?->hasPermission($permission);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Relationships
     */
    /**
     * The permission role, if one is assigned.
     *
     * Deliberately not named role(): `role` is already a column holding the
     * coarse account type, and Eloquent resolves an attribute before a
     * relationship — so $user->role must keep returning the string 'admin' /
     * 'dispatcher' / 'driver' that the whole panel branches on.
     */
    public function accessRole()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function documents()
    {
        return $this->hasMany(DriverDocument::class, 'driver_id');
    }

    public function incidents()
    {
        return $this->hasMany(TripIncident::class, 'driver_id');
    }

    /**
     * Subscription state
     *
     * A company with no live subscription can sign in and read, but cannot
     * change anything. That is deliberate: locking someone out of their own
     * records because a card expired would be worse than the unpaid bill.
     */
    public function hasActiveSubscription(): bool
    {
        // The platform admin is not a tenant and never needs a plan.
        if ($this->isAdmin()) {
            return true;
        }

        $company = $this->isCompanyOwner() ? $this : $this->dispatcher;

        if (! $company) {
            return false;
        }

        if ($company->trial_ends_at && $company->trial_ends_at->isFuture()) {
            return true;
        }

        return $company->subscription_status === 'active'
            && $company->subscription_plan_id !== null;
    }

    /**
     * Whether the company's tier includes a capability. Independent of the
     * user's own permissions — both have to pass.
     */
    public function planAllows(string $feature): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $company = $this->isCompanyOwner() ? $this : $this->dispatcher;
        $plan    = $company?->subscriptionPlan;

        /*
         * No plan at all is not the same as a plan that excludes something.
         * A company that has not subscribed can still look around the panel —
         * EnsureActiveSubscription already stops them changing anything, and
         * hiding every screen would leave them with nothing to evaluate
         * before paying. Tier gating is about which features a paying
         * customer gets.
         */
        if (! $plan) {
            return true;
        }

        return $plan->hasFeature($feature);
    }

    /**
     * Both gates at once, which is what the menu and the middleware want.
     */
    public function canUse(string $feature, ?string $permission = null): bool
    {
        if (! $this->planAllows($feature)) {
            return false;
        }

        return $permission === null || $this->hasPermission($permission);
    }

    /**
     * Free access granted by the platform admin, with an end date.
     *
     * Stored in trial_ends_at, which the subscription gate already honours.
     * The company works normally until that date and is then treated as
     * unsubscribed, so it can read the panel but not create anything until it
     * either pays or the admin extends the date.
     */
    public function hasFreeAccess(): bool
    {
        return $this->freeAccessEndsAt() !== null;
    }

    /**
     * When the free period runs out, or null if there is no live one.
     */
    public function freeAccessEndsAt(): ?\Illuminate\Support\Carbon
    {
        $company = $this->isCompanyOwner() ? $this : $this->dispatcher;

        return $company?->trial_ends_at && $company->trial_ends_at->isFuture()
            ? $company->trial_ends_at
            : null;
    }

    /**
     * A free period that has run out, for the "it expired on …" notice. A
     * company that has since started paying is not shown as expired.
     */
    public function freeAccessExpiredAt(): ?\Illuminate\Support\Carbon
    {
        $company = $this->isCompanyOwner() ? $this : $this->dispatcher;

        if (! $company?->trial_ends_at || $company->trial_ends_at->isFuture()) {
            return null;
        }

        return $company->subscription_status === 'active' ? null : $company->trial_ends_at;
    }

    public function subscriptionStatusLabel(): string
    {
        $company = $this->isCompanyOwner() ? $this : $this->dispatcher;

        if ($endsAt = $this->freeAccessEndsAt()) {
            return 'Free until ' . $endsAt->format('d M Y');
        }

        // Still paid up, just not renewing.
        if ($company?->cancels_at && $company->cancels_at->isFuture()) {
            return 'Ends ' . $company->cancels_at->format('d M Y');
        }

        if ($this->freeAccessExpiredAt()) {
            return 'Free access ended';
        }

        return match ($company?->subscription_status) {
            'active'    => 'Active',
            'past_due'  => 'Payment overdue',
            'cancelled' => 'Cancelled',
            default     => 'No subscription',
        };
    }

    public function subscriptionStatusClass(): string
    {
        $company = $this->isCompanyOwner() ? $this : $this->dispatcher;

        if ($this->freeAccessEndsAt()) {
            return 'bg-label-info';
        }

        if ($this->freeAccessExpiredAt()) {
            return 'bg-label-danger';
        }

        if ($company?->cancels_at && $company->cancels_at->isFuture()) {
            return 'bg-label-warning';
        }

        return match ($company?->subscription_status) {
            'active'    => 'bg-label-success',
            'past_due'  => 'bg-label-warning',
            'cancelled' => 'bg-label-danger',
            default     => 'bg-label-secondary',
        };
    }

    public function subscriptionPlan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    /**
     * The tier they have asked to move to at the end of the paid period.
     *
     * Not what they are on — planUsage(), the feature gates and everything
     * else keep reading subscriptionPlan() until the date arrives.
     */
    public function pendingPlan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'pending_plan_id');
    }

    /** A plan change is waiting for the current period to run out. */
    public function hasPendingPlanChange(): bool
    {
        return $this->pending_plan_id !== null;
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'dispatcher_id');
    }

    /**
     * How much of the plan this company is using.
     *
     * A null limit is unlimited, so percent stays null rather than becoming a
     * division by zero, and the bar renders as "no cap" instead of full.
     *
     * @return array<string, array{used:int, limit:?int, percent:?int}>
     */
    public function planUsage(): array
    {
        $plan      = $this->subscriptionPlan;
        $companyId = $this->companyId();

        $counts = [
            'vehicle' => Vehicle::where('dispatcher_id', $companyId)->count(),
            'driver'  => static::driversOf($companyId)->count(),
            // Trips are counted for the current month, which is the period a
            // monthly plan actually caps.
            'trip'    => Trip::where('dispatcher_id', $companyId)
                ->whereBetween('pickup_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
                ->count(),
        ];

        $usage = [];

        foreach ($counts as $resource => $used) {
            $limit = $plan?->limitFor($resource);

            $usage[$resource] = [
                'used'    => $used,
                'limit'   => $limit,
                'percent' => $limit ? min(100, (int) round($used / $limit * 100)) : null,
            ];
        }

        return $usage;
    }

    /**
     * Whether this company has room for one more of something.
     *
     * The subscription screen has always drawn these bars, but nothing ever
     * stopped the count going past the line — a company on a three-vehicle
     * tier could add thirty and only ever see a full bar. Reading the same
     * planUsage() the bars read keeps the number that blocks you and the
     * number you are shown from ever disagreeing.
     *
     * No plan means no cap: a company that has not subscribed is already
     * read-only, and capping them again would answer the wrong question.
     */
    public function withinPlanLimit(string $resource): bool
    {
        $usage = $this->planUsage()[$resource] ?? null;

        if (! $usage || $usage['limit'] === null) {
            return true;
        }

        return $usage['used'] < $usage['limit'];
    }

    /**
     * Why they cannot add another, in words the person can act on.
     */
    public function planLimitMessage(string $resource): string
    {
        $usage  = $this->planUsage()[$resource] ?? ['used' => 0, 'limit' => 0];
        $plural = ['vehicle' => 'vehicles', 'driver' => 'drivers', 'trip' => 'trips this month'];

        return 'Your plan covers ' . $usage['limit'] . ' ' . ($plural[$resource] ?? $resource)
            . ' and you have ' . $usage['used'] . '. Upgrade your plan to add more.';
    }

    public function dispatcher()
    {
        return $this->belongsTo(User::class, 'dispatcher_id');
    }

    /**
     * The company's drivers.
     *
     * Constrained to the driver role because panel staff are stored under the
     * same dispatcher_id; without this, every driver count would silently
     * include office users.
     */
    public function drivers()
    {
        return $this->hasMany(User::class, 'dispatcher_id')->where('role', 'driver');
    }

    /**
     * Panel users this company has added beneath itself.
     */
    public function staff()
    {
        return $this->hasMany(User::class, 'dispatcher_id')->where('role', 'dispatcher');
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class, 'dispatcher_id');
    }

    public function clients()
    {
        return $this->hasMany(Client::class, 'dispatcher_id');
    }

    public function trips()
    {
        return $this->hasMany(Trip::class, 'dispatcher_id');
    }

    public function assignedTrips()
    {
        return $this->hasMany(Trip::class, 'driver_id');
    }

    public function metas()
    {
        return $this->hasMany(UserMeta::class);
    }

    /**
     * The vehicle this driver runs. The pre-trip inspection needs one before
     * any checklist can be opened.
     */
    public function assignedVehicle()
    {
        return $this->hasOne(Vehicle::class, 'assigned_driver_id');
    }

    public function locations()
    {
        return $this->hasMany(DriverLocation::class, 'user_id');
    }

    public function inspections()
    {
        return $this->hasMany(VehicleInspection::class, 'driver_id');
    }

    public function inspectionItems()
    {
        return $this->hasMany(InspectionItem::class, 'dispatcher_id');
    }

    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages()
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }

    /**
     * The company this account belongs to.
     *
     * A dispatcher with no parent is the company itself. Staff added beneath
     * one are also stored with role 'dispatcher' but carry a dispatcher_id,
     * and must resolve to their employer rather than to themselves — otherwise
     * every tenant-scoped query would hand them an empty panel of their own.
     */
    public function companyId(): ?int
    {
        if ($this->isDispatcher()) {
            return $this->dispatcher_id ?: $this->id;
        }

        return $this->dispatcher_id;
    }

    /**
     * True for a top-level dispatcher account — a company, not its staff.
     */
    public function isCompanyOwner(): bool
    {
        return $this->isDispatcher() && ! $this->dispatcher_id;
    }

    /**
     * Companies: dispatcher accounts with no parent.
     */
    public function scopeCompanies(Builder $query): Builder
    {
        return $query->where('role', 'dispatcher')->whereNull('dispatcher_id');
    }

    /*
     * Both take a nullable company id. An admin belongs to no company, so
     * companyId() is null for them — and admins can reach the dispatcher
     * screens. Rejecting null would crash those pages; returning nothing is
     * the honest answer, because a platform admin has no drivers of their own.
     */
    public function scopeStaffOf(Builder $query, ?int $companyId): Builder
    {
        if ($companyId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('role', 'dispatcher')->where('dispatcher_id', $companyId);
    }

    public function scopeDriversOf(Builder $query, ?int $companyId): Builder
    {
        if ($companyId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('role', 'driver')->where('dispatcher_id', $companyId);
    }

    /**
     * Presence is considered stale once no ping or socket activity has been
     * seen for the configured window, so a crashed app does not leave a driver
     * showing as online forever.
     */
    public function isCurrentlyOnline(): bool
    {
        if (! $this->is_online || ! $this->last_seen_at) {
            return false;
        }

        $window = (int) config('readyroute.presence.offline_after_minutes', 5);

        return $this->last_seen_at->gt(now()->subMinutes($window));
    }

    /**
     * The one word the driver list puts on a driver's badge.
     *
     * Four states, in the order they override each other: an account that has
     * been switched off is inactive whatever else is true; a driver with a run
     * under way is on a trip; otherwise it comes down to whether their app is
     * connected. Working it out here keeps the panel, the API and the phone
     * from each inventing their own version.
     *
     * activeTripCount can be set by a list query that already counted, so a
     * page of drivers does not run one trip query per row.
     */
    public function dutyState(): string
    {
        if ($this->status !== 'active') {
            return 'inactive';
        }

        $onTrip = $this->relationLoaded('activeTrips')
            ? $this->activeTrips->isNotEmpty()
            : ($this->active_trips_count ?? \App\Models\Trip::forDriver($this->id)->active()->count()) > 0;

        if ($onTrip) {
            return 'on_trip';
        }

        return $this->isCurrentlyOnline() ? 'active' : 'off_duty';
    }

    public function dutyLabel(): string
    {
        return match ($this->dutyState()) {
            'on_trip'  => 'On Trip',
            'active'   => 'Active',
            'off_duty' => 'Off Duty',
            default    => 'Inactive',
        };
    }

    /**
     * Runs this driver has under way. Used as a counted relation so a list can
     * resolve dutyState() for every row in one extra query.
     */
    public function activeTrips()
    {
        return $this->hasMany(Trip::class, 'driver_id')->active();
    }

    /**
     * Meta Helpers
     */
    /**
     * Which days this driver works, read from their availability toggles.
     *
     * @return array{mon_fri:bool, sat:bool, sun:bool, on_call:bool}
     */
    public function availability(): array
    {
        return [
            'mon_fri' => (bool) $this->getMeta('availability_mon_fri'),
            'sat'     => (bool) $this->getMeta('availability_sat'),
            'sun'     => (bool) $this->getMeta('availability_sun'),
            'on_call' => (bool) $this->getMeta('availability_on_call'),
        ];
    }

    /**
     * Whether this driver works on a given date.
     *
     * On-call is deliberately not counted here. It means they can be reached
     * in an emergency, not that they are on the roster that day — treating it
     * as general availability would make every driver available every day,
     * since on-call tends to be left switched on.
     *
     * A date we cannot read is treated as available: the dispatcher has not
     * chosen a day yet, so there is nothing to rule anyone out on.
     */
    public function isAvailableOn(?\Illuminate\Support\Carbon $date): bool
    {
        if (! $date) {
            return true;
        }

        $availability = $this->availability();

        return match (true) {
            $date->isSaturday() => $availability['sat'],
            $date->isSunday()   => $availability['sun'],
            default             => $availability['mon_fri'],
        };
    }

    /**
     * Just the working days, short enough to sit inside a dropdown option.
     *
     * On-call is left out here: it does not change which days the driver can
     * be given a trip on, and in a list of options the extra words cost more
     * than they explain.
     */
    public function availabilityShort(): string
    {
        $availability = $this->availability();

        $days = array_keys(array_filter([
            'Mon-Fri' => $availability['mon_fri'],
            'Sat'     => $availability['sat'],
            'Sun'     => $availability['sun'],
        ]));

        if (empty($days)) {
            return $availability['on_call'] ? 'on-call' : 'no days';
        }

        return implode(', ', $days);
    }

    /**
     * The working days as something a person can read, e.g. "Mon-Fri, Sat".
     */
    public function availabilityLabel(): string
    {
        $availability = $this->availability();

        $days = array_keys(array_filter([
            'Mon-Fri' => $availability['mon_fri'],
            'Sat'     => $availability['sat'],
            'Sun'     => $availability['sun'],
        ]));

        if (empty($days)) {
            return $availability['on_call'] ? 'On-call only' : 'No days set';
        }

        return implode(', ', $days) . ($availability['on_call'] ? ' + on-call' : '');
    }

    public function getMeta(string $key, mixed $default = null): mixed
    {
        $meta = $this->metas->where('meta_key', $key)->first();
        if ($meta) {
            $value = json_decode($meta->meta_value, true);
            return json_last_error() === JSON_ERROR_NONE ? $value : $meta->meta_value;
        }

        return $default;
    }

    public function setMeta(string $key, mixed $value): void
    {
        $stringValue = is_array($value) || is_object($value) ? json_encode($value) : (string) $value;

        $this->metas()->updateOrCreate(
            ['meta_key' => $key],
            ['meta_value' => $stringValue]
        );
    }

    public function syncMetas(array $metas): void
    {
        foreach ($metas as $key => $value) {
            $this->setMeta($key, $value);
        }
    }

    /**
     * Get the user's avatar URL.
     */
    public function getAvatarUrlAttribute()
    {
        $image = $this->avatar ?? $this->profile_image;

        if ($image) {
            if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
                return $image;
            }

            if (str_starts_with($image, 'assets/') || str_starts_with($image, 'upload/') || str_starts_with($image, 'storage/')) {
                return asset($image);
            }

            /*
             * An upload whose file is no longer there would otherwise render
             * as a broken image for the rest of that account's life — the
             * column still holds a path, so nothing ever falls back. Checking
             * costs one stat and turns a visibly broken avatar into the
             * default one.
             */
            if (Storage::disk('public')->exists($image)) {
                return asset('storage/' . $image);
            }
        }

        return asset('assets/img/avatars/1.png');
    }
}