<?php

namespace Nugsoft\SignalBridge\Channels\Sandbox;

use Illuminate\Http\Client\Response;
use Nugsoft\SignalBridge\Channels\BaseChannelClient;
use Nugsoft\SignalBridge\Exceptions\SignalBridgeException;
use Nugsoft\SignalBridge\Exceptions\ValidationException;

/**
 * The sandbox's tools: what, on live, a customer or a provider would make
 * happen. They exist only in the sandbox — set SIGNALBRIDGE_SANDBOX=true.
 */
class SandboxClient extends BaseChannelClient
{
  public const STATUSES = ['delivered', 'failed', 'read'];

  /**
   * The magic numbers and what each does, and the template review rule.
   */
  public function magicNumbers(): array
  {
    return $this->handle($this->http()->get("{$this->baseUrl}/sandbox"));
  }

  /**
   * Simulate a customer's WhatsApp message to you. It arrives as a real one
   * does — a message.received or flow.completed webhook — linked to one of
   * your messages to that number (the one given, or your latest).
   *
   * @param  array  $options  type (text, button, interactive, flow, image, document, audio, video, sticker,
   *                          location), text, reply_to_message_id, reply_id (interactive), answers (flow),
   *                          latitude, longitude, name
   */
  public function customerMessage(string $from, array $options = []): array
  {
    if (trim($from) === '') {
      throw new ValidationException('The customer\'s number is required');
    }

    return $this->handle($this->http()->post("{$this->baseUrl}/sandbox/whatsapp/customer-message", ['from' => $from] + $options));
  }

  /**
   * Move one of your messages to an outcome now: delivered, failed, or — on
   * WhatsApp — read. Its webhooks fire as they would on live.
   */
  public function setMessageStatus(int $messageId, string $status): array
  {
    if (! in_array($status, self::STATUSES, true)) {
      throw new ValidationException('The status must be one of: ' . implode(', ', self::STATUSES));
    }

    return $this->handle($this->http()->post("{$this->baseUrl}/sandbox/messages/{$messageId}/status", ['status' => $status]));
  }

  /**
   * Free test credit, up to the sandbox's ceiling.
   */
  public function addCredit(): array
  {
    return $this->handle($this->http()->post("{$this->baseUrl}/sandbox/credit"));
  }

  protected function handle(Response $response): array
  {
    // A sandbox answers "Message not found." for a message that is not yours;
    // any other 404 at a sandbox path means this is not one.
    if ($response->status() === 404 && $response->json('message') !== 'Message not found.') {
      throw new SignalBridgeException('The sandbox tools exist only in the sandbox. Set SIGNALBRIDGE_SANDBOX=true and use a sandbox token.', 404);
    }

    if ($response->failed()) {
      $this->handleError($response);
    }

    return $this->parseResponse($response);
  }
}
