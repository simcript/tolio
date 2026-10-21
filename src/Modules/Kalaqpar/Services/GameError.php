<?php
namespace Src\Modules\Kalaqpar\Services;

final class GameError extends \RuntimeException
{
    public function __construct(public readonly string $key, string $message, public readonly int $status = 409)
    {
        parent::__construct($message);
    }
}
