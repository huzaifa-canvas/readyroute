<?php

namespace App\Models;

use App\Support\Permissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A named set of permissions for dispatcher-side staff.
 *
 * A role with a null dispatcher_id is a platform template the admin maintains;
 * anything else belongs to one company and is only ever visible to it.
 */
class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'dispatcher_id',
        'name',
        'slug',
        'description',
        'permissions',
        'is_system',
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_system'   => 'boolean',
    ];

    protected static function booted(): void
    {
        // Slugs are derived rather than typed: they only exist to make a role
        // addressable, and two roles named the same in one company is a
        // mistake worth catching at the unique index.
        static::saving(function (Role $role) {
            if (blank($role->slug)) {
                $role->slug = Str::slug($role->name) ?: 'role-' . Str::random(6);
            }
        });
    }

    public function dispatcher()
    {
        return $this->belongsTo(User::class, 'dispatcher_id');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'role_id');
    }

    /**
     * Roles a given company may use: its own, plus the platform templates.
     */
    public function scopeAvailableTo(Builder $query, ?int $dispatcherId): Builder
    {
        return $query->where(function ($q) use ($dispatcherId) {
            $q->whereNull('dispatcher_id');

            if ($dispatcherId) {
                $q->orWhere('dispatcher_id', $dispatcherId);
            }
        });
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? [], true);
    }

    /**
     * @return array<int, string>
     */
    public function permissionLabels(): array
    {
        return array_map(
            fn (string $permission) => Permissions::label($permission),
            $this->permissions ?? []
        );
    }

    public function permissionCount(): int
    {
        return count($this->permissions ?? []);
    }

    /**
     * A system role is the platform's own and must survive editing mistakes,
     * so deletion is refused for it everywhere.
     */
    public function isDeletable(): bool
    {
        return ! $this->is_system;
    }
}
