<?php

namespace Src\Modules\Kalaqpar\Services;

/**
 * Immutable Kalaqpar settings assembled from the `kalaqpar.*` config keys
 * (config/kalaqpar.php + KALAQPAR_* envs). Passed into services so tests
 * can build options without modifying configuration.
 */
final class GameOptions
{
    public function __construct(
        public readonly int $defaultRounds = 5,
        public readonly int $maxRounds = 9,
        public readonly int $revealSeconds = 12,
        public readonly int $roundMaxSeconds = 180,
        public readonly int $finishGraceSeconds = 8,
        public readonly int $lobbyTimeout = 1800,
        public readonly int $guessCooldown = 2,
        public readonly int $wrongGuessLimit = 10,
        public readonly int $heartbeatTimeout = 90,
    ) {
    }

    /** Builds options from the kalaqpar config namespace. */
    public static function fromConfig(): self
    {
        return new self(
            defaultRounds: (int) (config('kalaqpar.default_rounds') ?? 5),
            maxRounds: (int) (config('kalaqpar.max_rounds') ?? 9),
            revealSeconds: (int) (config('kalaqpar.reveal_seconds') ?? 12),
            roundMaxSeconds: (int) (config('kalaqpar.round_max_seconds') ?? 180),
            finishGraceSeconds: (int) (config('kalaqpar.finish_grace_seconds') ?? 8),
            lobbyTimeout: (int) (config('kalaqpar.lobby_timeout_seconds') ?? 1800),
            guessCooldown: (int) (config('kalaqpar.guess_cooldown') ?? 2),
            wrongGuessLimit: (int) (config('kalaqpar.wrong_guess_limit') ?? 10),
            heartbeatTimeout: (int) (config('kalaqpar.heartbeat_timeout') ?? 90),
        );
    }
}
