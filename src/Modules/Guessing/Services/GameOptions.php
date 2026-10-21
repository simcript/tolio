<?php

namespace Src\Modules\Guessing\Services;

/**
 * Immutable Guessing settings assembled from the `guessing.*` config keys
 * (config/guessing.php + GUESSING_* envs). Passed into services so tests
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

    /** Builds options from the guessing config namespace. */
    public static function fromConfig(): self
    {
        return new self(
            defaultRounds: (int) (config('guessing.default_rounds') ?? 5),
            maxRounds: (int) (config('guessing.max_rounds') ?? 9),
            revealSeconds: (int) (config('guessing.reveal_seconds') ?? 12),
            roundMaxSeconds: (int) (config('guessing.round_max_seconds') ?? 180),
            finishGraceSeconds: (int) (config('guessing.finish_grace_seconds') ?? 8),
            lobbyTimeout: (int) (config('guessing.lobby_timeout_seconds') ?? 1800),
            guessCooldown: (int) (config('guessing.guess_cooldown') ?? 2),
            wrongGuessLimit: (int) (config('guessing.wrong_guess_limit') ?? 10),
            heartbeatTimeout: (int) (config('guessing.heartbeat_timeout') ?? 90),
        );
    }
}
