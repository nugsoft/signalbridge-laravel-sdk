<?php

namespace Nugsoft\SignalBridge\Support;

/**
 * How SignalBridge counts SMS segments.
 *
 * This must agree with the gateway's own BalanceService::calculateSegments(),
 * character for character. When it does not, estimateCost() quotes one figure
 * and the invoice shows another — which is how this drifted before: the
 * alphabet here was missing the escape-table characters, so any message
 * containing a {placeholder} was treated as Unicode and over-quoted by half.
 */
final class MessageSegments
{
    /**
     * The GSM 03.38 alphabet as the gateway bills it.
     *
     * Note the real line feed and carriage return: a message with a newline is
     * plain text, not Unicode. The escape-table characters (^{}\[]~|€) are
     * counted as one character each, matching the gateway. Strict GSM-7
     * charges two septets for those.
     */
    public const GSM_7BIT_CHARSET = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞ^{}\\[]~€|ÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";

    public const GSM_SINGLE = 160;

    public const GSM_MULTIPART = 153;

    public const UNICODE_SINGLE = 70;

    public const UNICODE_MULTIPART = 67;

    /**
     * Segments a message body will be split into.
     */
    public static function count(string $message): int
    {
        if ($message === '') {
            return 1;
        }

        $characters = mb_str_split($message);

        if (self::isGsm7Bit($characters)) {
            $length = count($characters);

            return $length <= self::GSM_SINGLE
                ? 1
                : (int) ceil($length / self::GSM_MULTIPART);
        }

        // UCS-2 counts UTF-16 code units, so anything outside the Basic
        // Multilingual Plane — emoji, mostly — takes two.
        $units = 0;

        foreach ($characters as $character) {
            $units += strlen($character) > 3 ? 2 : 1;
        }

        return $units <= self::UNICODE_SINGLE
            ? 1
            : (int) ceil($units / self::UNICODE_MULTIPART);
    }

    /**
     * Estimated cost of sending a message at the given per-segment rate.
     */
    public static function estimateCost(string $message, float $segmentPrice): float
    {
        return self::count($message) * $segmentPrice;
    }

    /**
     * Whether every character falls inside the alphabet above.
     *
     * @param  array<int, string>  $characters
     */
    private static function isGsm7Bit(array $characters): bool
    {
        static $lookup = null;

        $lookup ??= array_flip(mb_str_split(self::GSM_7BIT_CHARSET));

        foreach ($characters as $character) {
            if (! isset($lookup[$character])) {
                return false;
            }
        }

        return true;
    }
}
