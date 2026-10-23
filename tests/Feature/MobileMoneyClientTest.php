<?php

namespace Nugsoft\SignalBridge\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Nugsoft\SignalBridge\Exceptions\InsufficientPermissionsException;
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

    /**
     * The gateway's answer to a collection it accepted.
     *
     * @return array<string, mixed>
     */
    private function accepted(array $overrides = []): array
    {
        return [
            'success' => true,
            'message' => 'Payment request initiated',
            'data' => [
                'reference' => '0b6f2c1e-7d3a-4a5b-9c8d-1e2f3a4b5c6d',
                'direction' => 'collection',
                'status' => 'pending',
                'status_message' => 'Request is being processed',
                'phone' => '256700000000',
                'amount' => '5000.00',
                'currency' => 'UGX',
                'created_at' => '2026-10-23T09:05:46+00:00',
                'settled_at' => null,
                ...$overrides,
            ],
        ];
    }

    #[Test]
    public function it_sends_only_the_fields_the_gateway_reads_and_returns_its_reference(): void
    {
        Http::fake(['*' => Http::response($this->accepted(), 202)]);

        $tx = $this->client()->mobileMoney()->initiate('256700000000', 5000.0, 'ugx', [
            'description' => 'Invoice payment',
            // Neither of these exists in the API; they must not be sent.
            'metadata' => ['order' => 1],
            'callback_url' => 'https://example.test/momo',
        ]);

        $this->assertSame('0b6f2c1e-7d3a-4a5b-9c8d-1e2f3a4b5c6d', $tx['data']['reference']);
        $this->assertSame('pending', $tx['data']['status']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://gateway.test/api/mobile-money/initiate'
                && $request->data() === [
                    'phone' => '256700000000',
                    'amount' => 5000.0,
                    'currency' => 'UGX',
                    'note' => 'Invoice payment',
                ];
        });
    }

    #[Test]
    public function a_note_takes_precedence_over_a_description(): void
    {
        Http::fake(['*' => Http::response($this->accepted(), 202)]);

        $this->client()->mobileMoney()->initiate('256700000000', 1000.0, 'UGX', [
            'note' => 'Shown to the payer',
            'description' => 'Ignored',
        ]);

        Http::assertSent(fn ($request) => $request->data()['note'] === 'Shown to the payer');
    }

    #[Test]
    public function it_refuses_a_reference_of_your_own(): void
    {
        Http::fake();

        try {
            $this->client()->mobileMoney()->initiate('256700000000', 5000.0, 'UGX', ['reference' => 'INV-1']);
            $this->fail('A reference of the caller\'s own was accepted.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('data.reference', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    #[Test]
    public function it_refuses_a_note_longer_than_the_gateway_takes(): void
    {
        Http::fake();

        $this->expectException(ValidationException::class);

        $this->client()->mobileMoney()->initiate('256700000000', 5000.0, 'UGX', ['note' => str_repeat('x', 101)]);
    }

    #[Test]
    public function it_refuses_an_amount_of_zero(): void
    {
        Http::fake();

        $this->expectException(ValidationException::class);

        $this->client()->mobileMoney()->initiate('256700000000', 0.0);
    }

    #[Test]
    public function collections_switched_off_on_the_gateway_are_reported_as_unavailable(): void
    {
        Http::fake(['*' => Http::response(['success' => false, 'message' => 'Mobile money collections are not available yet.'], 403)]);

        $this->expectException(ServiceUnavailableException::class);
        $this->expectExceptionMessage('Mobile money collections are not available yet.');

        $this->client()->mobileMoney()->initiate('256700000000', 5000.0);
    }

    #[Test]
    public function a_token_without_the_ability_is_still_a_permissions_error(): void
    {
        Http::fake(['*' => Http::response([
            'success' => false,
            'message' => 'This API token does not have permission to perform this action. Required ability: mobile-money:send.',
        ], 403)]);

        $this->expectException(InsufficientPermissionsException::class);

        $this->client()->mobileMoney()->initiate('256700000000', 5000.0);
    }

    #[Test]
    public function a_payment_the_provider_refused_says_why_and_keeps_the_transaction(): void
    {
        Http::fake(['*' => Http::response([
            'success' => false,
            'message' => 'The payment request was refused.',
            'data' => $this->accepted(['status' => 'failed', 'status_message' => 'Amount must be at least 500'])['data'],
        ], 422)]);

        try {
            $this->client()->mobileMoney()->initiate('256700000000', 100.0);
            $this->fail('A refused payment did not throw.');
        } catch (ValidationException $e) {
            $this->assertSame('The payment request was refused: Amount must be at least 500', $e->getMessage());
            $this->assertSame('0b6f2c1e-7d3a-4a5b-9c8d-1e2f3a4b5c6d', $e->getData()['reference']);
            $this->assertSame('failed', $e->getData()['status']);
        }
    }

    #[Test]
    public function it_verifies_a_transaction_by_its_reference(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'data' => $this->accepted(['status' => 'completed'])['data']])]);

        $result = $this->client()->mobileMoney()->verify('0b6f2c1e-7d3a-4a5b-9c8d-1e2f3a4b5c6d');

        $this->assertSame('completed', $result['data']['status']);

        Http::assertSent(
            fn ($request) => $request->url() === 'https://gateway.test/api/mobile-money/transactions/0b6f2c1e-7d3a-4a5b-9c8d-1e2f3a4b5c6d'
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
