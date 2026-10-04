<?php

declare(strict_types=1);

namespace App\Domain\FollowUps\Exceptions;

use DomainException;

final class FollowUpRuleViolation extends DomainException
{
    public static function quoteIsNotSent(): self
    {
        return new self('Solo las cotizaciones enviadas pueden recibir un seguimiento.');
    }

    public static function pendingAlreadyExists(): self
    {
        return new self('Esta cotización ya tiene un seguimiento pendiente.');
    }

    public static function notPending(): self
    {
        return new self('Solo un seguimiento pendiente puede actualizarse.');
    }

    public static function assigneeIsUnavailable(): self
    {
        return new self('El responsable debe tener una membresía activa en esta empresa.');
    }
}
