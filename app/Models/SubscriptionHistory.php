<?php

namespace App\Models;

use App\Enums\SubscriptionState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionHistory extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'subscription_id',
        'previous_status',
        'new_status',
        'reason',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Get the subscription this history belongs to
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Get a readable description of the state change
     */
    public function getDescription(): string
    {
        $from = $this->previous_status ?? 'created';
        $to = $this->new_status;
        return ucfirst($from) . ' → ' . ucfirst($to) . ': ' . $this->reason;
    }

    /**
     * Scope: Get latest histories
     */
    public function scopeLatest($query, $limit = 10)
    {
        return $query->orderByDesc('created_at')->limit($limit);
    }
}
