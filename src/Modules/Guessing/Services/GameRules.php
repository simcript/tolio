<?php

namespace Src\Modules\Guessing\Services;

use Src\Modules\Guessing\Exceptions\GuessingException;

/**
 * Pure Guessing game rules: Persian text normalization, secret generation,
 * round timing and scoring. No database access — every rule is testable in
 * isolation and everything is driven by an injected `now` timestamp, which
 * is what makes the timing and concurrency scenarios deterministic.
 */
final class GameRules
{
    public const CODE_ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    private const DIACRITICS_RANGE = "\u{064B}-\u{065F}\u{0670}\u{0640}";
    private const ARABIC_ALIASES = [
        // Keyboard/legacy aliases that normalize to the same Persian letter.
        'ي' => 'ی', 'ى' => 'ی',
        'ك' => 'ک',
        'ۀ' => 'ه', 'ة' => 'ه',
        'أ' => 'ا', 'إ' => 'ا', 'ٱ' => 'ا', 'آ' => 'ا',
    ];

    /**
     * Normalizes a guess (or a dictionary word) for comparison: Persian
     * letters unified, diacritics/tatweel/ZWNJ dropped, whitespace and
     * punctuation removed. The comparison key of "آ" collapses to "ا" so
     * keyboard aliases never break a correct guess.
     */
    public static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = strtr($value, self::ARABIC_ALIASES);
        $value = preg_replace('~[' . self::DIACRITICS_RANGE . ']~u', '', $value) ?? $value;
        $value = preg_replace("~[\s\u{200C}\u{200E}\u{200F}\u{061B}\u{061F}\p{P}]~u", '', $value) ?? $value;

        return $value;
    }

    /**
     * @param list<string> $words
     * @param list<string> $exclude Normalized words the room already used
     */
    public static function pickWord(array $words, array $exclude = []): string
    {
        $candidates = array_values(array_diff($words, $exclude));

        if ($candidates === []) {
            // Every word in the pack was played: reset the exclusion and
            // reuse the pack rather than stalling the game.
            $candidates = $words;
        }

        return $candidates[random_int(0, count($candidates) - 1)];
    }

    public static function newRoomCode(): string
    {
        $code = '';
        $max = mb_strlen(self::CODE_ALPHABET) - 1;
        for ($i = 0; $i < 6; $i++) {
            $code .= self::CODE_ALPHABET[random_int(0, $max)];
        }

        return $code;
    }

    /**
     * Round length: one reveal per interval plus a grace tail after the
     * final letter, capped by the configured maximum.
     */
    public static function roundEndsAt(int $startedAt, int $wordLength, int $revealSeconds, int $graceSeconds, int $maxSeconds): int
    {
        $natural = $startedAt + ($wordLength * $revealSeconds) + $graceSeconds;

        return min($natural, $startedAt + $maxSeconds);
    }

    /**
     * Points awarded for a correct guess. Fewer revealed letters mean an
     * earlier — and therefore harder — solve, so they score more.
     */
    public static function basePoints(int $revealedCount): int
    {
        return max(4, 16 - (3 * $revealedCount));
    }

    /**
     * Streak bonus for winning consecutive rounds: the second consecutive
     * win pays +3 and the bonus grows to at most +12. $winningStreak
     * includes the round being scored (first win = 1, so no bonus).
     */
    public static function streakBonus(int $winningStreak): int
    {
        return $winningStreak <= 1 ? 0 : min(12, 3 * ($winningStreak - 1));
    }

    /** Character count ignoring joining/ZWNJ separators, used by validation. */
    public static function wordLength(string $word): int
    {
        return mb_strlen(str_replace("\u{200C}", '', $word), 'UTF-8');
    }

    /**
     * Validates a submitted guess payload. Throws a domain error that the
     * API layer maps to a 4xx JSON response.
     */
    public static function validateGuess(string $guess): string
    {
        $normalized = self::normalize($guess);

        if ($normalized === '') {
            throw new GuessingException(
                GuessingException::VALIDATION,
                'حدس خالی است؛ یک کلمه بنویس.',
            );
        }

        if (mb_strlen($normalized, 'UTF-8') > 64) {
            throw new GuessingException(
                GuessingException::VALIDATION,
                'حدس بیش از حد طولانی است.',
            );
        }

        return $normalized;
    }
}
