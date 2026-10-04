<?php

declare(strict_types=1);

namespace App\Domain\FollowUps;

enum FollowUpOutcome: string
{
    case Contacted = 'contacted';
    case NoAnswer = 'no_answer';
    case CustomerReviewing = 'customer_reviewing';
    case NeedsChanges = 'needs_changes';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Contacted => 'Contactado',
            self::NoAnswer => 'Sin respuesta',
            self::CustomerReviewing => 'Cliente evaluando',
            self::NeedsChanges => 'Requiere cambios',
            self::Other => 'Otro',
        };
    }
}
