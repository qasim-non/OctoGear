<?php

namespace App\Enums;

/**
 * Defines the status of an order throughout its lifecycle.
 *
 * Used in: orders.status column
 *
 * Lifecycle:
 *   pending → negotiating → paid → completed
 *       ↓          ↓
 *   rejected   cancelled
 *
 * - Pending: Customer sent the order, waiting for store response
 * - Rejected: Store rejected the order (specific orders only)
 * - Negotiating: Store accepted but price is being discussed
 * - Paid: Customer paid for the component
 * - Completed: Customer received the component
 * - Cancelled: Customer cancelled the order
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Rejected = 'rejected';
    case Negotiating = 'negotiating';
    case Paid = 'paid';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * Can this order transition to the given status?
     *
     * This method contains the business rules for status transitions.
     * Use it in OrderService to validate transitions.
     */
    public function canTransitionTo(self $newStatus): bool
    {
        return match ($this) {
            // Pending → can be accepted (→ negotiating), rejected, or cancelled
            self::Pending => in_array($newStatus, [
                self::Negotiating,
                self::Rejected,
                self::Cancelled,
            ]),

            // Negotiating → can be paid, or cancelled
            self::Negotiating => in_array($newStatus, [
                self::Paid,
                self::Cancelled,
            ]),

            // Paid → can be completed (customer received the part)
            self::Paid => in_array($newStatus, [
                self::Completed,
            ]),

            // Terminal states — no transitions allowed
            self::Rejected, self::Completed, self::Cancelled => false,
        };
    }

    /**
     * Is this order still active (not in a terminal state)?
     */
    public function isActive(): bool
    {
        return !in_array($this, [
            self::Rejected,
            self::Completed,
            self::Cancelled,
        ]);
    }

    /**
     * Human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Rejected => 'Rejected',
            self::Negotiating => 'Negotiating',
            self::Paid => 'Paid',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }
}
