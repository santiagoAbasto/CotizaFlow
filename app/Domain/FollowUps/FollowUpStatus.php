<?php

declare(strict_types=1);

namespace App\Domain\FollowUps;

enum FollowUpStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Completed => 'Completado',
            self::Cancelled => 'Cancelado',
        };
    }
}
