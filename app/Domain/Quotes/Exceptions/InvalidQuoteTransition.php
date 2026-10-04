<?php

declare(strict_types=1);

namespace App\Domain\Quotes\Exceptions;

use App\Domain\Quotes\QuoteStatus;
use DomainException;

final class InvalidQuoteTransition extends DomainException
{
    public static function between(QuoteStatus $from, QuoteStatus $to): self
    {
        return new self("No se puede cambiar una cotización de {$from->label()} a {$to->label()}.");
    }

    public static function notDue(): self
    {
        return new self('La cotización todavía no venció.');
    }

    public static function validityEnded(): self
    {
        return new self('No se puede resolver una cotización cuya vigencia ya venció.');
    }
}
