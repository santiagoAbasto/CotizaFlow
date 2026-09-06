<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Tenancy\Actions\ActivateCompanyForUser;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Domain\Tenancy\Exceptions\ActiveCompanyUnavailable;
use App\Domain\Tenancy\Exceptions\CompanyNotAuthorized;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use ReflectionNamedType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Binds the tenant boundary from server-side state only.
 *
 * The active company is read from the session, never from the request: a
 * company id in the route, query, body or headers is ignored by construction.
 * The session value is re-authorised against a live membership on every
 * request, so revoking a membership takes effect immediately.
 *
 * There is deliberately NO silent fallback. Previously, a session pointing at a
 * company the user could no longer use caused another company to be selected
 * automatically — which on a POST/PUT/PATCH/DELETE meant the write silently
 * landed in the wrong tenant. When the selection is not usable the request is
 * refused with a domain code and the stale selection is cleared; choosing a
 * replacement is an explicit user action.
 *
 * Must run AFTER authentication and BEFORE SubstituteBindings; the priority is
 * pinned in bootstrap/app.php.
 */
final class ResolveTenantContext
{
    public const SESSION_KEY = 'tenancy.active_company_id';

    /**
     * Routes that must stay reachable without an active company — the future
     * company-selection and onboarding endpoints.
     */
    public const EXEMPT_ROUTE_PREFIX = 'tenancy.';

    public function __construct(private readonly ActivateCompanyForUser $activate) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            if ($this->routeBindsTenantModel($request)) {
                // Commercial routes must carry auth middleware. This defensive
                // boundary prevents an accidental omission from becoming a 500
                // that exposes TenantContextMissing before the controller runs.
                throw new HttpException(Response::HTTP_UNAUTHORIZED, 'Authentication is required.');
            }

            return $next($request);
        }

        $selection = $request->hasSession()
            ? $request->session()->get(self::SESSION_KEY)
            : null;

        if (is_int($selection)) {
            try {
                ($this->activate)($user, $selection);

                return $next($request);
            } catch (CompanyNotAuthorized) {
                // Stale or revoked selection: drop it and refuse rather than
                // quietly continuing inside a different tenant.
                $request->session()->forget(self::SESSION_KEY);

                $this->refuse($request, $user, ActiveCompanyUnavailable::selectionInvalid());

                return $next($request);
            }
        }

        $this->refuse($request, $user, ActiveCompanyUnavailable::selectionRequired());

        return $next($request);
    }

    /**
     * Refuses an unusable tenant state, unless the route is exempt.
     *
     * A user with no memberships at all gets 403 rather than 409: there is
     * nothing they could select, so asking them to choose would be a dead end.
     *
     * Exempt routes continue with NO tenant bound, so any tenant-scoped query
     * they attempt still fails closed.
     *
     * @throws ActiveCompanyUnavailable
     */
    private function refuse(Request $request, mixed $user, ActiveCompanyUnavailable $whenSelectable): void
    {
        if ($this->isExempt($request)) {
            return;
        }

        throw $user->activeMemberships()->exists()
            ? $whenSelectable
            : ActiveCompanyUnavailable::noActiveMembership();
    }

    private function isExempt(Request $request): bool
    {
        $name = $request->route()?->getName();

        return is_string($name) && str_starts_with($name, self::EXEMPT_ROUTE_PREFIX);
    }

    private function routeBindsTenantModel(Request $request): bool
    {
        foreach ($request->route()?->signatureParameters(['subClass' => Model::class]) ?? [] as $parameter) {
            $type = $parameter->getType();

            if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $model = $type->getName();

            if (in_array(BelongsToTenant::class, class_uses_recursive($model), true)) {
                return true;
            }
        }

        return false;
    }
}
