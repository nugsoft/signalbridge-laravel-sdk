<?php

namespace Nugsoft\SignalBridge\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Nugsoft\SignalBridge\Exceptions\InsufficientBalanceException;
use Nugsoft\SignalBridge\Exceptions\RateLimitedException;
use Nugsoft\SignalBridge\Exceptions\ServiceUnavailableException;
use Nugsoft\SignalBridge\Exceptions\UnauthorizedException;
use Nugsoft\SignalBridge\Exceptions\ValidationException;
use Nugsoft\SignalBridge\SignalBridgeClient;
use Nugsoft\SignalBridge\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class SmsClientTest extends TestCase
{
    private function client(): SignalBridgeClient
    {
        return new SignalBridgeClient('test-token', 'https://gateway.test/api');
    }

    #[Test]
    public function it_sends_an_sms(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'data' => ['message_id' => 7]])]);

        $result = $this->client()->sms()->send('256700000000', 'Hello');

        $this->assertSame(7, $result['data']['message_id']);
        Http::assertSent(fn ($r) => $r->url() === 'https://gateway.test/api/sms/send'
            && $r['recipient'] === '256700000000');
    }

    #[Test]
    public function it_reads_one_message_status(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'data' => ['id' => 7, 'status' => 'delivered']])]);

        $result = $this->client()->sms()->status(7);

        $this->assertSame('delivered', $result['data']['status']);
        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://gateway.test/api/sms/messages/7'));
    }

    #[Test]
    public function it_asks_the_vendor_live_when_refreshing(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'data' => []])]);

        $this->client()->sms()->status(7, refresh: true);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'refresh=1'));
    }

    #[Test]
    public function it_lists_messages_and_joins_batch_ids(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'data' => [], 'summary' => ['total' => 0]])]);

        $this->client()->sms()->messages(['ids' => [1, 2, 3], 'status' => 'delivered']);

        Http::assertSent(fn ($r) => str_contains(urldecode($r->url()), 'ids=1,2,3')
            && str_contains($r->url(), 'status=delivered'));
    }

    #[Test]
    public function disbursement_fails_clearly_rather_than_looking_like_a_bad_url(): void
    {
        Http::fake();

        $this->expectException(ServiceUnavailableException::class);
        $this->expectExceptionMessageMatches('/not available yet/');

        $this->client()->mobileMoney()->disburse('256700000000', 5000);
    }

    #[DataProvider('errorStatuses')]
    #[Test]
    public function it_maps_api_errors_to_typed_exceptions(int $status, string $message, string $expected): void
    {
        Http::fake(['*' => Http::response(['success' => false, 'message' => $message], $status)]);

        $this->expectException($expected);

        $this->client()->sms()->send('256700000000', 'Hello');
    }

    public static function errorStatuses(): array
    {
        return [
            'unauthorized' => [401, 'Unauthenticated.', UnauthorizedException::class],
            'insufficient balance' => [402, 'Insufficient balance.', InsufficientBalanceException::class],
            'validation' => [422, 'The given data was invalid.', ValidationException::class],
            'rate limited' => [429, 'Too many requests.', RateLimitedException::class],
            'unavailable' => [503, 'SMS service is currently unavailable.', ServiceUnavailableException::class],
        ];
    }
}
