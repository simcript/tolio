<?php

namespace Src\Modules\Guessing\Exceptions;

use Pano\Foundation\Exception as PanoException;
use Pano\Kernel\HttpStatusEnum;

/**
 * Guessing domain errors. Carries a stable machine code plus a Persian
 * message that the JSON API echoes verbatim and the UI can display.
 */
final class GuessingException extends PanoException
{
    public const VALIDATION = 'validation_error';
    public const NOT_MEMBER = 'not_member';
    public const NOT_FOUND = 'not_found';
    public const CONFLICT = 'conflict';
    public const RATE_LIMITED = 'rate_limited';
    public const CSRF = 'csrf_mismatch';
    public const TOO_LATE = 'too_late';
    public const GUEST_REQUIRED = 'guest_required';

    /** @param array<string, mixed> $details extra response fields (scope, retry_after, …) */
    public function __construct(
        public readonly string $error,
        string $message,
        private readonly int $httpStatus = 400,
        private readonly array $details = [],
    ) {
        parent::__construct($message, 0, HttpStatusEnum::from($httpStatus));
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    /** @return array{ok: false, error: string, message: string}|array<string, mixed> */
    public function body(): array
    {
        return [
            'ok' => false,
            'error' => $this->error,
            'message' => $this->getMessage(),
            ...$this->details,
        ];
    }
}
