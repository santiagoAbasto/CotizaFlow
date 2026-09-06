<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Enums;

/**
 * A user's role is a property of their membership in one company, never of the
 * user: the same person may be an owner of one tenant and a salesperson in
 * another.
 */
enum MembershipRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Sales = 'sales';

    public function canManageCompany(): bool
    {
        return $this === self::Owner || $this === self::Admin;
    }

    public function canManageMembers(): bool
    {
        return $this === self::Owner || $this === self::Admin;
    }

    /**
     * Only an owner may hand over or dissolve the tenant itself.
     */
    public function canDeleteCompany(): bool
    {
        return $this === self::Owner;
    }
}
