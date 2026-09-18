<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One message in the dispatch chat. A thread is the pair of a dispatcher
 * company and one of its drivers, so both are stored on every row and no
 * separate threads table is needed.
 */
class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'dispatcher_id',
        'driver_id',
        'sender_id',
        'receiver_id',
        'body',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function dispatcher()
    {
        return $this->belongsTo(User::class, 'dispatcher_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function scopeThread(Builder $query, int $dispatcherId, int $driverId): Builder
    {
        return $query
            ->where('dispatcher_id', $dispatcherId)
            ->where('driver_id', $driverId);
    }

    public function scopeUnreadFor(Builder $query, int $userId): Builder
    {
        return $query->where('receiver_id', $userId)->whereNull('read_at');
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
