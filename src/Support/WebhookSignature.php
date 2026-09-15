<?php

namespace Nugsoft\SignalBridge\Support;

use Illuminate\Http\Request;

/**
 * Verifies that an inbound webhook really came from SignalBridge.
 *
 * The gateway signs the RAW request body with HMAC-SHA256 and sends the digest
 * in X-SignalBridge-Signature. Two details are easy to get wrong by hand, and
 * both are silent:
 *
 *  - Sign the raw body, not a re-encoded copy of the parsed payload. Re-encoding
 *    only matches while your JSON key order happens to match the sender's.
 *  - Compare in constant time. A plain === leaks the expected digest a byte at
 *    a time to anyone willing to measure.
 */
final class WebhookSignature
{
    public const HEADER = 'X-SignalBridge-Signature';

    public const EVENT_HEADER = 'X-SignalBridge-Event';

    /**
     * Verify a Laravel request carrying a SignalBridge webhook.
     */
    public static function verifyRequest(Request $request, string $secret): bool
    {
        return self::verify(
            $request->getContent(),
            (string) $request->header(self::HEADER, ''),
            $secret
        );
    }

    /**
     * Verify a raw body against a signature header.
     *
     * @param  string  $payload    The raw request body, exactly as received
     * @param  string  $signature  Header value, with or without the "sha256=" prefix
     */
    public static function verify(string $payload, string $signature, string $secret): bool
    {
        if ($signature === '' || $secret === '') {
            return false;
        }

        $provided = str_starts_with($signature, 'sha256=')
            ? substr($signature, 7)
            : $signature;

        return hash_equals(hash_hmac('sha256', $payload, $secret), $provided);
    }

    /**
     * The signature a given body should carry. Useful in your own tests.
     */
    public static function sign(string $payload, string $secret): string
    {
        return 'sha256='.hash_hmac('sha256', $payload, $secret);
    }
}
