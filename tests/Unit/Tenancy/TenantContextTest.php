<?php

declare(strict_types=1);

use App\Domain\Tenancy\Exceptions\TenantContextMissing;
use App\Domain\Tenancy\TenantContext;

beforeEach(function (): void {
    $this->context = new TenantContext;
});

it('starts unbound and fails closed', function (): void {
    expect($this->context->has())->toBeFalse()
        ->and(fn () => $this->context->id())->toThrow(TenantContextMissing::class);
});

it('restores the previous boundary after runAs returns', function (): void {
    $this->context->enter(7);

    $this->context->runAs(9, fn () => expect($this->context->id())->toBe(9));

    expect($this->context->id())->toBe(7);
});

it('restores the previous boundary when the callback throws', function (): void {
    $this->context->enter(7);

    try {
        $this->context->runAs(9, function (): void {
            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect($this->context->id())->toBe(7);
});

it('leaves no boundary behind when runAs throws from an unbound state', function (): void {
    try {
        $this->context->runAs(9, function (): void {
            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect($this->context->has())->toBeFalse();
});

it('nests without losing the outer boundary', function (): void {
    $this->context->runAs(1, function (): void {
        $this->context->runAs(2, function (): void {
            $this->context->runAs(3, fn () => expect($this->context->id())->toBe(3));
            expect($this->context->id())->toBe(2);
        });
        expect($this->context->id())->toBe(1);
    });

    expect($this->context->has())->toBeFalse();
});

it('releases and restores the boundary around runWithoutTenant', function (): void {
    $this->context->enter(7);

    $this->context->runWithoutTenant(fn () => expect($this->context->has())->toBeFalse());

    expect($this->context->id())->toBe(7);
});

it('restores the boundary when runWithoutTenant throws', function (): void {
    $this->context->enter(7);

    try {
        $this->context->runWithoutTenant(function (): void {
            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect($this->context->id())->toBe(7);
});

it('is not a cross-tenant bypass: it only removes the boundary', function (): void {
    $this->context->enter(7);

    // The scope stays installed, so a tenant-scoped query still fails closed
    // rather than seeing every tenant's rows.
    $this->context->runWithoutTenant(function (): void {
        expect(fn () => $this->context->id())->toThrow(TenantContextMissing::class);
    });

    expect($this->context->id())->toBe(7);
});
