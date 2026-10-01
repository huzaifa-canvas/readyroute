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
        $lastNumber = static::whereNotNull('driver_code')
            ->orderByDesc('id')
            ->value('driver_code');

        $next = $lastNumber
            ? ((int) preg_replace('/\D/', '', $lastNumber)) + 1
            : 1;

        return 'DRV-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
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
     * Meta Helpers
     */
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