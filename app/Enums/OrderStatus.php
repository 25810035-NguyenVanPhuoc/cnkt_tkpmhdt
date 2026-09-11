<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Delivering = 'delivering';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function nextAllowed(): ?self
    {
        return match ($this) {
            self::Pending => self::Confirmed,
            self::Confirmed => self::Delivering,
            self::Delivering => self::Completed,
            self::Completed, self::Cancelled => null,
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return $this->nextAllowed() === $to;
    }

    public function isCancellable(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed, self::Delivering], true);
    }
}
