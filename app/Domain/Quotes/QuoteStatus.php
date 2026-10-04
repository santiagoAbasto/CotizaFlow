<?php

declare(strict_types=1);

namespace App\Domain\Quotes;

enum QuoteStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Sent => 'Enviada',
            self::Accepted => 'Aceptada',
            self::Rejected => 'Rechazada',
            self::Expired => 'Vencida',
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Draft => $target === self::Sent,
            self::Sent => in_array($target, [self::Accepted, self::Rejected, self::Expired], true),
            self::Accepted, self::Rejected, self::Expired => false,
        };
    }
}
