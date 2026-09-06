<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * The request cannot proceed because there is no usable active company.
 *
 * Carries a stable domain code so the frontend can distinguish "pick a company"
 * from "your access was revoked" without parsing prose.
 */
final class ActiveCompanyUnavailable extends RuntimeException implements HttpExceptionInterface
{
    public const NO_ACTIVE_MEMBERSHIP = 'NO_ACTIVE_MEMBERSHIP';

    public const ACTIVE_COMPANY_REQUIRED = 'ACTIVE_COMPANY_REQUIRED';

    public const ACTIVE_MEMBERSHIP_INVALID = 'ACTIVE_MEMBERSHIP_INVALID';

    private function __construct(
        public readonly string $domainCode,
        private readonly int $status,
        string $message,
    ) {
        parent::__construct($message);
    }

    /** The user belongs to no company at all: nothing to select. */
    public static function noActiveMembership(): self
    {
        return new self(
            self::NO_ACTIVE_MEMBERSHIP,
            Response::HTTP_FORBIDDEN,
            'This account has no active company membership.',
        );
    }

    /** The user has companies but has not chosen one. */
    public static function selectionRequired(): self
    {
        return new self(
            self::ACTIVE_COMPANY_REQUIRED,
            Response::HTTP_CONFLICT,
            'An active company must be selected before this request.',
        );
    }

    /** The chosen company stopped being usable (revoked or suspended). */
    public static function selectionInvalid(): self
    {
        return new self(
            self::ACTIVE_MEMBERSHIP_INVALID,
            Response::HTTP_CONFLICT,
            'The selected company is no longer available to this account.',
        );
    }

    public function getStatusCode(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return [];
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'code' => $this->domainCode,
            'message' => $this->getMessage(),
        ], $this->status);
    }
}
