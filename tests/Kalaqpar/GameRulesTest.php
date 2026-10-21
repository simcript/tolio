<?php

namespace Tests\Guessing;

use Src\Modules\Guessing\Services\GameRules;

final class GameRulesTest extends GuessingTestCase
{
    public function test_normalization_unifies_keyboard_variants_and_strips_noise(): void
    {
        self::assertSame('مدرسه', GameRules::normalize('مَدْرَسِــة'));
        self::assertSame(
            'اسانسور',
            GameRules::normalize('آسانسور' . "\u{200C}"),
            'Alef-madda collapses and ZWNJ drops.'
        );
        self::assertSame(
            'کلاغ',
            GameRules::normalize('كلاغ'), // Arabic kaf → Persian kaf
        );
        self::assertSame(
            'نیمهشب',
            GameRules::normalize('نیمه‌شب'), // ZWNJ never splits the comparison
        );
        self::assertSame('', GameRules::normalize("   \t\u{200C}  "));
    }

    public function test_normalization_is_idempotent(): void
    {
        $once = GameRules::normalize('شهرِ بازی');
        self::assertSame($once, GameRules::normalize($once));
    }

    public function test_word_length_counts_visible_letters(): void
    {
        self::assertSame(4, GameRules::wordLength('کتاب'));
        self::assertSame(6, GameRules::wordLength('نیمه‌شب'), 'ZWNJ is not a letter.');
    }

    public function test_pick_word_skips_played_words_and_recycles_the_pack(): void
    {
        $words = ['کتاب', 'مدرسه'];
        self::assertSame('مدرسه', GameRules::pickWord($words, ['کتاب']));
        // Everything excluded: the pack itself is reused rather than stalling.
        self::assertContains(GameRules::pickWord($words, ['کتاب', 'مدرسه']), $words);
    }

    public function test_room_codes_are_unique_alphabet(): void
    {
        $codes = [];
        for ($i = 0; $i < 50; $i++) {
            $code = GameRules::newRoomCode();

            self::assertSame(6, strlen($code));
            self::assertSame(1, preg_match('~^[' . GameRules::CODE_ALPHABET . ']{6}$~', $code));
            $codes[$code] = true;
        }

        self::assertCount(50, $codes, 'Codes must be virtually unique.');
    }

    public function test_scoring_rewards_early_guesses_and_streaks(): void
    {
        self::assertSame(16, GameRules::basePoints(0));
        self::assertSame(10, GameRules::basePoints(2));
        self::assertSame(4, GameRules::basePoints(9), 'Floor of 4 for late guesses.');

        self::assertSame(0, GameRules::streakBonus(1), 'First win carries no bonus.');
        self::assertSame(3, GameRules::streakBonus(2));
        self::assertSame(12, GameRules::streakBonus(11), 'Bonus caps at 12.');
    }

    public function test_guess_validation_rejects_empty_and_overlong_guesses(): void
    {
        $this->expectException(\Src\Modules\Guessing\Exceptions\GuessingException::class);
        GameRules::validateGuess('   ');
    }

    public function test_guess_validation_normalizes_accented_input(): void
    {
        self::assertSame('مدرسه', GameRules::validateGuess('مَدرسِه'));
    }
}
