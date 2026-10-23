<?php

namespace Nugsoft\SignalBridge\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Nugsoft\SignalBridge\Exceptions\SignalBridgeException;
use Nugsoft\SignalBridge\Exceptions\ValidationException;
use Nugsoft\SignalBridge\SignalBridgeClient;
use Nugsoft\SignalBridge\Support\MagicNumbers;
use Nugsoft\SignalBridge\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class SandboxTest extends TestCase
{
    #[Test]
    public function the_sandbox_setting_points_the_client_at_the_sandbox(): void
    {
        $sandbox = new SignalBridgeClient('sandbox-token', SignalBridgeClient::SANDBOX_URL);
        $live = new SignalBridgeClient('live-token', SignalBridgeClient::LIVE_URL);

        $this->assertTrue($sandbox->isSandbox());
        $this->assertFalse($live->isSandbox());
    }

    #[Test]
    public function the_sandbox_tools_hit_their_routes(): void
    {
        Http::fake(['*' => Http::response(['success' => true])]);
        $tools = (new SignalBridgeClient('sandbox-token', SignalBridgeClient::SANDBOX_URL))->sandboxTools();

        $tools->magicNumbers();
        $tools->customerMessage(MagicNumbers::DELIVERED, ['type' => 'interactive', 'reply_to_message_id' => 42, 'reply_id' => 'confirm_42']);
        $tools->setMessageStatus(42, 'read');
        $tools->addCredit();

        Http::assertSent(fn (Request $r) => $r->method() === 'GET' && $r->url() === SignalBridgeClient::SANDBOX_URL . '/sandbox');
        Http::assertSent(fn (Request $r) => $r->url() === SignalBridgeClient::SANDBOX_URL . '/sandbox/whatsapp/customer-message'
            && $r['from'] === '256700000001' && $r['reply_id'] === 'confirm_42');
        Http::assertSent(fn (Request $r) => $r->url() === SignalBridgeClient::SANDBOX_URL . '/sandbox/messages/42/status' && $r['status'] === 'read');
        Http::assertSent(fn (Request $r) => $r->url() === SignalBridgeClient::SANDBOX_URL . '/sandbox/credit');
    }

    #[Test]
    public function the_sandbox_tools_on_live_say_where_they_live(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Not Found'], 404)]);

        $this->expectException(SignalBridgeException::class);
        $this->expectExceptionMessage('only in the sandbox');

        (new SignalBridgeClient('live-token', SignalBridgeClient::LIVE_URL))->sandboxTools()->addCredit();
    }

    #[Test]
    public function a_message_that_is_not_yours_is_still_reported_as_such(): void
    {
        Http::fake(['*' => Http::response(['success' => false, 'message' => 'Message not found.'], 404)]);

        $this->expectExceptionMessage('Message not found.');

        (new SignalBridgeClient('sandbox-token', SignalBridgeClient::SANDBOX_URL))->sandboxTools()->setMessageStatus(99, 'delivered');
    }

    #[Test]
    public function an_unknown_status_is_refused_before_sending(): void
    {
        Http::fake();

        try {
            (new SignalBridgeClient('sandbox-token', SignalBridgeClient::SANDBOX_URL))->sandboxTools()->setMessageStatus(1, 'lost');
            $this->fail('An unknown status was sent');
        } catch (ValidationException) {
            Http::assertNothingSent();
        }
    }
}
