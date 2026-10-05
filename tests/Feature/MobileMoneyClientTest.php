<?php

namespace Nugsoft\SignalBridge\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Nugsoft\SignalBridge\Exceptions\ServiceUnavailableException;
use Nugsoft\SignalBridge\Exceptions\ValidationException;
use Nugsoft\SignalBridge\SignalBridgeClient;
use Nugsoft\SignalBridge\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class MobileMoneyClientTest extends TestCase
{
    private function client(): SignalBridgeClient
    {
        return new SignalBridgeClient('test-token', 'https://gateway.test/api');
    }

    #[Test]
    public function it_sends_only_the_fields_the_gateway_reads(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'data' => ['transaction_id' => 'tx-1']])]);

        $this->client()->mobileMoney()->initiate('256700000000', 5000.0, 'ugx', [
            'reference' => 'INV-1',
            'description' => 'Invoice payment',
            // Neither of these exists in the API; they must not be sent.
            'metadata' => ['order' => 1],
            'callback_url' => 'https://example.test/momo',
        ]);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->url() === 'https://gateway.test/api/mobile-money/initiate'
                && $body === [
                    'phone' => '256700000000',
                    'amount' => 5000.0,
                    'currency' => 'UGX',
                    'reference' => 'INV-1',
                    'note' => 'Invoice payment',
                ];
        });
    }

    #[Test]
    public function a_note_takes_precedence_over_a_description(): void
    {
        Http::fake(['*' => Http::response(['success' => true])]);

        $this->client()->mobileMoney()->initiate('256700000000', 100.0, 'UGX', [
            'note' => 'Shown to the payer',
            'description' => 'Ignored',
        ]);

        Http::assertSent(fn ($request) => $request->data()['note'] === 'Shown to the payer');
    }

    #[Test]
    public function it_refuses_an_amount_of_zero(): void
    {
        Http::fake();

        $this->expectException(ValidationException::class);

        $this->client()->mobileMoney()->initiate('256700000000', 0.0);
    }

    #[Test]
    public function it_verifies_a_transaction(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'data' => ['status' => 'completed']])]);

        $result = $this->client()->mobileMoney()->verify('tx-1');

        $this->assertSame('completed', $result['data']['status']);

        Http::assertSent(
            fn ($request) => $request->url() === 'https://gateway.test/api/mobile-money/transactions/tx-1'
        );
    }

    #[Test]
    public function disbursement_reports_that_it_is_unavailable(): void
    {
        Http::fake();

        $this->expectException(ServiceUnavailableException::class);

        $this->client()->mobileMoney()->disburse('256700000000', 5000.0);
    }
}
