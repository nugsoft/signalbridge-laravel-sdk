<?php

namespace Nugsoft\SignalBridge\Tests\Unit;

use Nugsoft\SignalBridge\Support\MessageSegments;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * These cases are mirrored in the gateway's own MessageSegmentsTest. If the two
 * ever disagree, estimateCost() quotes a figure the invoice will not match.
 */
class MessageSegmentsTest extends TestCase
{
    #[Test]
    public function a_template_placeholder_stays_gsm(): void
    {
        // Regression: {} were missing from the alphabet, so any templated
        // message was treated as Unicode and over-quoted by half.
        $this->assertSame(1, MessageSegments::count('Hi {name} '.str_repeat('a', 90)));
        $this->assertSame(2, MessageSegments::count('Hi {name} '.str_repeat('a', 190)));
    }

    #[Test]
    public function a_newline_stays_gsm(): void
    {
        $this->assertSame(1, MessageSegments::count(str_repeat('a', 74)."\n".str_repeat('b', 75)));
    }

    #[Test]
    public function every_escape_table_character_stays_gsm(): void
    {
        foreach (['^', '{', '}', '\\', '[', ']', '~', '|', '€'] as $char) {
            $this->assertSame(
                1,
                MessageSegments::count($char.str_repeat('a', 120)),
                "[{$char}] should not force a message to Unicode"
            );
        }
    }

    #[DataProvider('gsmLengths')]
    #[Test]
    public function gsm_messages_split_at_160_then_153(int $length, int $expected): void
    {
        $this->assertSame($expected, MessageSegments::count(str_repeat('a', $length)));
    }

    public static function gsmLengths(): array
    {
        return [
            'one segment' => [160, 1],
            'just over' => [161, 2],
            'two segments' => [306, 2],
            'three segments' => [307, 3],
        ];
    }

    #[DataProvider('unicodeLengths')]
    #[Test]
    public function unicode_messages_split_at_70_then_67(int $length, int $expected): void
    {
        $this->assertSame($expected, MessageSegments::count(str_repeat("\u{0416}", $length)));
    }

    public static function unicodeLengths(): array
    {
        return [
            'one segment' => [70, 1],
            'just over' => [71, 2],
            'two segments' => [134, 2],
            'three segments' => [135, 3],
        ];
    }

    #[Test]
    public function emoji_count_as_two_units(): void
    {
        $this->assertSame(3, MessageSegments::count(str_repeat("\u{1F600}", 100)));
    }

    #[Test]
    public function characters_inside_the_bmp_count_as_one_unit(): void
    {
        $this->assertSame(1, MessageSegments::count(str_repeat("\u{4E2D}", 70)));
    }

    #[Test]
    public function an_empty_message_is_one_segment(): void
    {
        $this->assertSame(1, MessageSegments::count(''));
    }

    #[Test]
    public function cost_is_segments_times_rate(): void
    {
        $this->assertSame(100.0, MessageSegments::estimateCost(str_repeat('a', 200), 50.0));
    }
}
