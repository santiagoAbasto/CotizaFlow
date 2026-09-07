<?php

declare(strict_types=1);

namespace App\Domain\Quotes\Exceptions;

use DomainException;

final class QuoteNotEditable extends DomainException
{
    public static function make(): self
    {
        return new self('Solo las cotizaciones en borrador pueden editarse.');
    }
}
