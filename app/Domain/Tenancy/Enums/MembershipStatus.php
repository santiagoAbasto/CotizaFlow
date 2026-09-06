<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Enums;

enum MembershipStatus: string
{
    case Invited = 'invited';
    case Active = 'active';
    case Suspended = 'suspended';

    /**
     * Only an active membership may be used to enter a tenant. Invited users
     * have not accepted yet; suspended users have been revoked.
     */
    public function grantsAccess(): bool
    {
        return $this === self::Active;
    }
}
