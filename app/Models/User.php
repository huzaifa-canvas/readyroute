<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

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
     * Relationships
     */
    public function dispatcher()
    {
        return $this->belongsTo(User::class, 'dispatcher_id');
    }

    public function drivers()
    {
        return $this->hasMany(User::class, 'dispatcher_id');
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
     * A driver's own company. Dispatchers are their own company, which keeps
     * tenant scoping uniform for both roles.
     */
    public function companyId(): ?int
    {
        return $this->isDispatcher() ? $this->id : $this->dispatcher_id;
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
            return asset('storage/' . $image);
        }

        return asset('assets/img/avatars/1.png');
    }
}