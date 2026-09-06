<?php

declare(strict_types=1);

namespace Tests\Fixtures;

/** Negative fixture: a job carrying a DTO that wraps a model. Not queueable. */
final class BadDtoJob
{
    public function __construct(public CustomerBag $bag) {}
}
