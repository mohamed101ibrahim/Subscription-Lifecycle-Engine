<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FailedPayment extends Model
{
    use HasFactory;

    public $timestamps = false; // Migration doesn't create updated_at

    protected $fillable = [
        'subscription_id',
        'amount',
        'currency',
        'failure_reason',
        'provider_error_code',
        'provider_error_message',
        'failed_at',
        'recovered',
        'recovered_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'failed_at' => 'datetime',
        'recovered_at' => 'datetime',
        'recovered' => 'boolean',
    ];

    /**
     * Get the subscription this payment belongs to
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Mark payment as recovered
     */
    public function markAsRecovered(): void
    {
        $this->update([
            'recovered' => true,
            'recovered_at' => now(),
        ]);
    }

    /**
     * Get formatted amount
     */
    public function getFormattedAmount(): string
    {
        return number_format($this->amount, 2) . ' ' . $this->currency;
    }

    /**
     * Scope: Get unrecovered payments
     */
    public function scopeUnrecovered($query)
    {
        return $query->where('recovered', false);
    }

    /**
     * Scope: Get recovered payments
     */
    public function scopeRecovered($query)
    {
        return $query->where('recovered', true);
    }

    /**
     * Scope: Get payments from a specific time period
     */
    public function scopeFromDate($query, $date)
    {
        return $query->where('failed_at', '>=', $date);
    }

    /**
     * Scope: Get payments until a specific date
     */
    public function scopeUntilDate($query, $date)
    {
        return $query->where('failed_at', '<=', $date);
    }
}
