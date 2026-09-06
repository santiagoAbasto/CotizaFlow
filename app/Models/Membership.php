<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Enums\MembershipRole;
use App\Domain\Tenancy\Enums\MembershipStatus;
use Database\Factories\MembershipFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Places a globally-identified user inside one tenant.
 *
 * Deliberately NOT tenant-scoped: membership lookup is what establishes the
 * tenant boundary, so it must be queryable before a boundary exists. Every
 * read of this model must therefore constrain company_id explicitly.
 *
 * Nothing here is mass assignable: role, status and both foreign keys are
 * authorisation-bearing and may only be set by domain code.
 */
class Membership extends Model
{
    /** @use HasFactory<MembershipFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => MembershipRole::class,
            'status' => MembershipStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function grantsAccess(): bool
    {
        return $this->status->grantsAccess();
    }
}
