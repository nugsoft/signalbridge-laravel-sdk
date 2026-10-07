<?php

namespace Nugsoft\SignalBridge\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Nugsoft\SignalBridge\Exceptions\ValidationException;
use Nugsoft\SignalBridge\SignalBridgeClient;
use Nugsoft\SignalBridge\Support\WebhookSignature;
use Nugsoft\SignalBridge\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class WhatsAppClientTest extends TestCase
{
    private function whatsapp()
    {
        return (new SignalBridgeClient('test-token', 'https://gateway.test/api'))->whatsapp();
    }

    #[Test]
    public function it_sends_a_template_with_plain_variables(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'data' => ['message_id' => 9, 'cost' => 60]])]);

        $result = $this->whatsapp()->sendTemplate('256700000000', 'fee_reminder', ['John', 'UGX 50,000']);

        $this->assertSame(9, $result['data']['message_id']);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://gateway.test/api/whatsapp/send'
            && $r['template'] === 'fee_reminder'
            && $r['variables'] === ['John', 'UGX 50,000']
            && ! isset($r['components']));
    }

    #[Test]
    public function it_sends_a_template_that_starts_with_a_file(): void
    {
        Http::fake(['*' => Http::response(['success' => true])]);

        $this->whatsapp()->sendTemplate('256700000000', 'weekly_report', ['Kampala'], [
            'header' => ['type' => 'document', 'url' => 'https://files.example.com/r.pdf', 'filename' => 'r.pdf'],
            'flow' => ['data' => ['week' => 41]],
        ]);

        Http::assertSent(fn (Request $r) => $r['header'] === ['type' => 'document', 'url' => 'https://files.example.com/r.pdf', 'filename' => 'r.pdf']
            && $r['flow'] === ['data' => ['week' => 41]]);
    }

    #[Test]
    public function it_refuses_the_old_components_structure_with_an_explanation(): void
    {
        Http::fake();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/plain list/');

        $this->whatsapp()->sendTemplate('256700000000', 'order_confirmation', [
            ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => 'John']]],
        ]);
    }

    #[Test]
    public function it_sends_free_text(): void
    {
        Http::fake(['*' => Http::response(['success' => true])]);

        $this->whatsapp()->send('256700000000', 'Thanks for your reply');

        Http::assertSent(fn (Request $r) => $r['message'] === 'Thanks for your reply' && ! isset($r['template']));
    }

    #[Test]
    public function it_sends_a_flow(): void
    {
        Http::fake(['*' => Http::response(['success' => true])]);

        $this->whatsapp()->sendFlow('256700000000', 'spa_booking', 'Book your next session', 'Book now', ['screen' => 'BOOKING', 'data' => ['service' => 'massage']]);

        Http::assertSent(fn (Request $r) => $r['flow'] === [
            'name' => 'spa_booking',
            'body' => 'Book your next session',
            'button' => 'Book now',
            'screen' => 'BOOKING',
            'data' => ['service' => 'massage'],
        ]);
    }

    #[Test]
    public function it_manages_templates(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'data' => []])]);

        $client = $this->whatsapp();
        $client->createTemplate(['name' => 'fee_reminder', 'category' => 'utility', 'body' => 'Hi {{1}}, pay soon.', 'examples' => ['John']]);
        $client->listTemplates('approved');
        $client->getTemplate(4, refresh: true);
        $client->deleteTemplate(4);

        Http::assertSentInOrder([
            fn (Request $r) => $r->method() === 'POST' && $r->url() === 'https://gateway.test/api/whatsapp/templates' && $r['name'] === 'fee_reminder',
            fn (Request $r) => $r->method() === 'GET' && $r->url() === 'https://gateway.test/api/whatsapp/templates?status=approved',
            fn (Request $r) => $r->method() === 'GET' && $r->url() === 'https://gateway.test/api/whatsapp/templates/4?refresh=1',
            fn (Request $r) => $r->method() === 'DELETE' && $r->url() === 'https://gateway.test/api/whatsapp/templates/4',
        ]);
    }

    #[Test]
    public function it_manages_flows(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'data' => [], 'endpoint_secret' => 's3'])]);

        $client = $this->whatsapp();
        $created = $client->createFlow(['name' => 'spa_booking', 'categories' => ['appointment_booking'], 'flow_json' => ['version' => '7.3'], 'endpoint_url' => 'https://spa.example.com/flow']);
        $client->updateFlow(3, ['endpoint_url' => 'https://spa.example.com/v2/flow']);
        $client->publishFlow(3);
        $client->regenerateFlowSecret(3);
        $client->deleteFlow(3);

        $this->assertSame('s3', $created['endpoint_secret']);
        Http::assertSentInOrder([
            fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/whatsapp/flows') && $r['flow_json'] === ['version' => '7.3'],
            fn (Request $r) => $r->method() === 'PUT' && str_ends_with($r->url(), '/whatsapp/flows/3'),
            fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/whatsapp/flows/3/publish'),
            fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/whatsapp/flows/3/regenerate-secret'),
            fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/whatsapp/flows/3'),
        ]);
    }

    #[Test]
    public function it_reads_received_messages_and_downloads_their_files(): void
    {
        Http::fake([
            'gateway.test/api/whatsapp/received/7/media' => Http::response('%PDF-1.4', 200, ['Content-Type' => 'application/pdf']),
            'gateway.test/api/whatsapp/received*' => Http::response(['success' => true, 'data' => [['id' => 7, 'type' => 'document']]]),
        ]);

        $client = $this->whatsapp();

        $this->assertSame(7, $client->received(['since' => '2026-10-07T00:00:00+03:00'])['data'][0]['id']);
        $this->assertSame('%PDF-1.4', $client->downloadMedia(7));
    }

    #[Test]
    public function a_forwarded_flow_call_verifies_with_the_webhook_helper(): void
    {
        $body = json_encode(['event' => 'flow.data_exchange', 'action' => 'INIT', 'flow' => 'spa_booking']);

        $this->assertTrue(WebhookSignature::verify($body, WebhookSignature::sign($body, 'flow-secret'), 'flow-secret'));
        $this->assertFalse(WebhookSignature::verify($body, WebhookSignature::sign($body, 'other'), 'flow-secret'));
    }
}
