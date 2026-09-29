<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One dated observation about a client, written by a dispatcher.
 *
 * Drivers read these on the trip screen but can never write one, so nothing on
 * the driver side touches this model except to list it.
 */
class ClientNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'dispatcher_id',
        'author_id',
        'body',
        'visible_to_driver',
    ];

    protected $casts = [
        'visible_to_driver' => 'boolean',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function dispatcher()
    {
        return $this->belongsTo(User::class, 'dispatcher_id');
    }

    /**
     * What a driver is allowed to see. Used by the trip detail endpoint.
     */
    public function scopeVisibleToDriver(Builder $query): Builder
    {
        return $query->where('visible_to_driver', true);
    }

    public function scopeNewestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('created_at');
    }

    /**
     * The author may have been removed from the company since writing this,
     * so the name is resolved defensively.
     */
    public function authorName(): string
    {
        return $this->author?->name ?? 'Removed user';
    }
}
