<?php

namespace Nugsoft\SignalBridge\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Nugsoft\SignalBridge\Exceptions\ValidationException;
use Nugsoft\SignalBridge\SignalBridgeClient;
use Nugsoft\SignalBridge\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class BalanceTest extends TestCase
{
    private function client(): SignalBridgeClient
    {
        return new SignalBridgeClient('test-token', 'https://gateway.test/api');
    }

    #[Test]
    public function it_reads_the_sms_balance_unless_asked_for_whatsapp(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'data' => ['channel' => 'whatsapp']])]);

        $this->client()->getBalance('UGX');
        $this->client()->getBalance('UGX', 'WhatsApp');

        Http::assertSent(fn ($request) => $request->url() === 'https://gateway.test/api/balance?currency=UGX');
        Http::assertSent(fn ($request) => $request->url() === 'https://gateway.test/api/balance?currency=UGX&channel=whatsapp');
    }

    #[Test]
    public function it_filters_transactions_by_channel(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'data' => []])]);

        $this->client()->getTransactions(['channel' => 'whatsapp', 'type' => 'debit']);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'channel=whatsapp'));
    }

    #[Test]
    public function a_channel_with_no_balance_is_refused_before_sending(): void
    {
        Http::fake();

        try {
            $this->client()->getBalance('UGX', 'ussd');
            $this->fail('A balance channel that does not exist was sent.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('sms, whatsapp', $e->getMessage());
        }

        Http::assertNothingSent();
    }
}
