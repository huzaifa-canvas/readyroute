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
        'dispatcher_id',
        'phone_number',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
        ];
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