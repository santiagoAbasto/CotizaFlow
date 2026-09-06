<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Enums\MembershipStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A global identity. A user is not owned by a tenant and carries no role of
 * its own: both live on Membership, so the same person can belong to several
 * companies with a different role in each. See ADR 0001.
 *
 * Email is globally unique, which keeps login and password reset unambiguous.
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'memberships')
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }

    /**
     * The membership for one company, or null when the user has none.
     *
     * Status is intentionally NOT filtered here: callers must decide whether a
     * suspended or merely invited membership is acceptable, so that a refusal
     * is an explicit decision rather than an empty result.
     */
    public function membershipFor(Company|int $company): ?Membership
    {
        $companyId = $company instanceof Company ? $company->getKey() : $company;

        return $this->memberships()->where('company_id', $companyId)->first();
    }

    public function activeMemberships(): HasMany
    {
        return $this->memberships()->where('status', MembershipStatus::Active->value);
    }
}
