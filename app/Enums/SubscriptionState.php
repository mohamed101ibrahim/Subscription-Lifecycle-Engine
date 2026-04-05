<?php

namespace App\Enums;

enum SubscriptionState: string
{
    case TRIALING = 'trialing';
    case ACTIVE = 'active';
    case PAST_DUE = 'past_due';
    case CANCELED = 'canceled';

    /**
     * Get all valid states that can be transitioned to from current state
     * 
     * @return array<self>
     */
    public function validTransitionsTo(): array
    {
        return match ($this) {
            self::TRIALING => [self::ACTIVE, self::CANCELED],
            self::ACTIVE => [self::PAST_DUE, self::CANCELED],
            self::PAST_DUE => [self::ACTIVE, self::CANCELED],
            self::CANCELED => [], // Terminal state - no outbound transitions
        };
    }

    /**
     * Check if a transition is valid
     */
    public function canTransitionTo(self $newState): bool
    {
        return in_array($newState, $this->validTransitionsTo());
    }

    /**
     * Get a human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::TRIALING => 'In Trial',
            self::ACTIVE => 'Active',
            self::PAST_DUE => 'Past Due',
            self::CANCELED => 'Canceled',
        };
    }

    /**
     * Get a description of the state
     */
    public function description(): string
    {
        return match ($this) {
            self::TRIALING => 'Subscription is in trial period',
            self::ACTIVE => 'Subscription is active and valid',
            self::PAST_DUE => 'Payment failed, in grace period',
            self::CANCELED => 'Subscription has been canceled',
        };
    }

    /**
     * Get all possible states
     * 
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::TRIALING->value => self::TRIALING->label(),
            self::ACTIVE->value => self::ACTIVE->label(),
            self::PAST_DUE->value => self::PAST_DUE->label(),
            self::CANCELED->value => self::CANCELED->label(),
        ];
    }

    /**
     * Check if state is a terminal state
     */
    public function isTerminal(): bool
    {
        return $this === self::CANCELED;
    }

    /**
     * Check if subscription has access in this state
     */
    public function hasAccess(): bool
    {
        return in_array($this, [self::TRIALING, self::ACTIVE, self::PAST_DUE]);
    }
}
