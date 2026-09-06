<?php

declare(strict_types=1);

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Models\Customer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Finder\Finder;
use Tests\Fixtures\BadCollectionJob;
use Tests\Fixtures\BadDirectModelJob;
use Tests\Fixtures\BadDtoJob;
use Tests\Fixtures\RecordCustomerNameJob;

/**
 * Enforces the payload rule from docs/architecture/TENANCY.md: queued work
 * carries a company id and scalar entity ids, never a serialized tenant-scoped
 * model.
 *
 * A queued model is rehydrated through a fresh query executed with whatever
 * boundary the worker happens to hold — the exact situation tenant scoping
 * exists to prevent.
 *
 * SCOPE: this is a guardrail, not a proof. It inspects constructor signatures,
 * their docblock array hints, and one level of DTO containment. It cannot see a
 * model assigned to a public property after construction, hidden inside an
 * untyped array, or wrapped more than one level deep. The rule is upheld by
 * design review; this test only catches the common regressions cheaply.
 */
function tenantScopedModels(): array
{
    $models = [];

    foreach (Finder::create()->files()->in(app_path('Models'))->name('*.php') as $file) {
        $class = 'App\\Models\\'.$file->getFilenameWithoutExtension();

        if (class_exists($class) && in_array(BelongsToTenant::class, class_uses_recursive($class), true)) {
            $models[] = $class;
        }
    }

    return $models;
}

function queueableClasses(): array
{
    $found = [];
    $roots = [app_path() => 'App\\', base_path('tests/Fixtures') => 'Tests\\Fixtures\\'];

    foreach ($roots as $path => $namespace) {
        if (! is_dir($path)) {
            continue;
        }

        foreach (Finder::create()->files()->in($path)->name('*.php') as $file) {
            $class = $namespace.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());

            if (class_exists($class) && is_subclass_of($class, ShouldQueue::class)) {
                $found[] = $class;
            }
        }
    }

    return $found;
}

/** @return list<string> type names named by a reflection type */
function typeNames(?ReflectionType $type): array
{
    if ($type instanceof ReflectionNamedType) {
        return [$type->getName()];
    }

    if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
        return array_merge(...array_map(fn ($t) => typeNames($t), $type->getTypes()));
    }

    return [];
}

/** Detects a tenant-scoped model named anywhere in a docblock type hint. */
function docblockNamesTenantModel(?string $doc, string $parameter, array $tenantModels): bool
{
    if ($doc === null || $doc === false) {
        return false;
    }

    // Non-greedy: a generic hint like `Collection<int, Customer>` contains a
    // space, so the type cannot be matched as a single whitespace-free token.
    if (! preg_match('/@param\s+(.+?)\s+\$'.preg_quote($parameter, '/').'\b/', $doc, $m)) {
        return false;
    }

    foreach ($tenantModels as $model) {
        $short = substr((string) strrchr($model, '\\'), 1);

        if (str_contains($m[1], $short)) {
            return true;
        }
    }

    return false;
}

function violationsFor(string $class, array $tenantModels): array
{
    $violations = [];
    $reflection = new ReflectionClass($class);
    $constructor = $reflection->getConstructor();
    $doc = $constructor?->getDocComment() ?: null;

    foreach ($constructor?->getParameters() ?? [] as $parameter) {
        $names = typeNames($parameter->getType());
        $label = $class.'::__construct($'.$parameter->getName().')';

        foreach ($names as $name) {
            // 1. A tenant-scoped model passed directly.
            foreach ($tenantModels as $model) {
                if ($name === $model || (class_exists($name) && is_subclass_of($name, $model))) {
                    $violations[] = $label.' takes '.$name;
                }
            }

            // 2. A collection or array whose docblock names a tenant model.
            $isContainer = in_array($name, ['array', 'iterable'], true)
                || (class_exists($name) && is_subclass_of($name, Traversable::class));

            if ($isContainer && docblockNamesTenantModel($doc, $parameter->getName(), $tenantModels)) {
                $violations[] = $label.' is a collection of tenant-scoped models';
            }

            // 3. One level of DTO containment.
            if (class_exists($name) && ! in_array($name, $tenantModels, true) && ! is_subclass_of($name, Model::class)) {
                foreach ((new ReflectionClass($name))->getProperties() as $property) {
                    foreach (typeNames($property->getType()) as $propertyType) {
                        if (in_array($propertyType, $tenantModels, true)) {
                            $violations[] = $label.' carries '.$name.'::$'.$property->getName();
                        }
                    }
                }
            }
        }
    }

    return $violations;
}

it('finds the tenant-scoped models it is meant to protect', function (): void {
    expect(tenantScopedModels())->toContain(Customer::class);
});

it('scans at least one queueable class so the rule is not vacuous', function (): void {
    expect(queueableClasses())->toContain(RecordCustomerNameJob::class);
});

it('never accepts a tenant-scoped model as a queued job payload', function (): void {
    $tenantModels = tenantScopedModels();
    $violations = [];

    foreach (queueableClasses() as $class) {
        $violations = [...$violations, ...violationsFor($class, $tenantModels)];
    }

    expect($violations)->toBe([], implode(PHP_EOL, [
        'Queued classes must carry a company id and scalar entity ids:',
        ...$violations,
    ]));
});

it('detects a direct model, a collection of models and a DTO that wraps one', function (): void {
    // Proves the detector actually fires, rather than passing because it sees
    // nothing. These fixtures exist only inside this test file's namespace.
    $tenantModels = [Customer::class];

    expect(violationsFor(BadDirectModelJob::class, $tenantModels))->toHaveCount(1)
        ->and(violationsFor(BadCollectionJob::class, $tenantModels))->toHaveCount(1)
        ->and(violationsFor(BadDtoJob::class, $tenantModels))->toHaveCount(1);
});
