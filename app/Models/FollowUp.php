<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\FollowUps\FollowUpOutcome;
use App\Domain\FollowUps\FollowUpStatus;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\FollowUpFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['assigned_to', 'scheduled_at', 'note'])]
class FollowUp extends Model
{
    /** @use HasFactory<FollowUpFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'status' => FollowUpStatus::class,
            'outcome' => FollowUpOutcome::class,
        ];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function previousFollowUp(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_follow_up_id');
    }

    public function nextFollowUp(): HasOne
    {
        return $this->hasOne(self::class, 'previous_follow_up_id');
    }

    /** @param Builder<FollowUp> $query */
    public function scopePending(Builder $query): void
    {
        $query->where($query->qualifyColumn('status'), FollowUpStatus::Pending->value);
    }
}
