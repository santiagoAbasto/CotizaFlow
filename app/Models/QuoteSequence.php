<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

final class QuoteSequence extends Model
{
    use BelongsToTenant;

    protected $fillable = ['next_number'];
}
