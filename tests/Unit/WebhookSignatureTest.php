<?php

namespace Nugsoft\SignalBridge\Tests\Unit;

use Illuminate\Http\Request;
use Nugsoft\SignalBridge\Support\WebhookSignature;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class WebhookSignatureTest extends TestCase
{
    private const SECRET = 'top-secret';

    #[Test]
    public function it_accepts_a_correct_signature(): void
    {
        $body = '{"event":"message.delivered","message_id":1}';

        $this->assertTrue(WebhookSignature::verify(
            $body,
            hash_hmac('sha256', $body, self::SECRET),
            self::SECRET
        ));
    }

    #[Test]
    public function it_accepts_the_sha256_prefixed_form(): void
    {
        $body = '{"event":"message.sent"}';

        $this->assertTrue(WebhookSignature::verify(
            $body,
            WebhookSignature::sign($body, self::SECRET),
            self::SECRET
        ));
    }

    #[Test]
    public function it_rejects_a_tampered_body(): void
    {
        $signature = WebhookSignature::sign('{"amount":10}', self::SECRET);

        $this->assertFalse(WebhookSignature::verify('{"amount":100000}', $signature, self::SECRET));
    }

    #[Test]
    public function it_rejects_the_wrong_secret(): void
    {
        $body = '{"event":"message.sent"}';

        $this->assertFalse(WebhookSignature::verify(
            $body,
            WebhookSignature::sign($body, self::SECRET),
            'not-the-secret'
        ));
    }

    #[Test]
    public function it_rejects_a_missing_signature_or_secret(): void
    {
        $this->assertFalse(WebhookSignature::verify('{}', '', self::SECRET));
        $this->assertFalse(WebhookSignature::verify('{}', 'abc', ''));
    }

    #[Test]
    public function it_verifies_a_laravel_request_against_the_raw_body(): void
    {
        $body = '{"z":1,"a":2}';   // key order that a re-encode would not reproduce

        $request = Request::create('/hooks', 'POST', [], [], [], [
            'HTTP_X_SIGNALBRIDGE_SIGNATURE' => WebhookSignature::sign($body, self::SECRET),
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $this->assertTrue(WebhookSignature::verifyRequest($request, self::SECRET));
    }
}
