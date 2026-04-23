<?php

namespace Nugsoft\SignalBridge\Channels\Ussd;

use Nugsoft\SignalBridge\Channels\BaseChannelClient;
use Nugsoft\SignalBridge\Exceptions\ValidationException;

/**
 * USSD channel client.
 *
 * USSD support is planned. The method signatures and API endpoint paths below reflect
 * the intended contract. Methods will be fully functional once the server-side USSD
 * engine is released.
 *
 * Planned endpoints:
 *   POST   /ussd/push
 *   GET    /ussd/sessions/{id}
 *   POST   /ussd/sessions/{id}/respond
 *
 * @todo Activate once SignalBridge USSD engine is live.
 */
class UssdClient extends BaseChannelClient
{
  /**
   * Push a USSD notification to a subscriber.
   *
   * The subscriber receives a USSD prompt on their handset. If they respond,
   * SignalBridge fires a webhook to your callback_url with the session ID and input.
   *
   * @param  string  $phone        Subscriber phone in E.164 format (e.g. '256700000000')
   * @param  string  $serviceCode  USSD service code (e.g. '*123#')
   * @param  string  $message      Text to display (max 182 characters)
   * @param  array   $options {
   *   @type string   $reference     Your reference for this push (idempotency key)
   *   @type string   $callback_url  Webhook URL to receive session events
   *   @type array    $metadata      Arbitrary key-value pairs stored for your records
   * }
   * @return array{data: array{session_id: string, status: string}}
   */
  public function push(string $phone, string $serviceCode, string $message, array $options = []): array
  {
    if (empty(trim($phone))) {
      throw new ValidationException('Subscriber phone number is required');
    }

    if (empty(trim($serviceCode))) {
      throw new ValidationException('USSD service code is required');
    }

    if (empty(trim($message))) {
      throw new ValidationException('Message content is required');
    }

    if (mb_strlen($message) > 182) {
      throw new ValidationException('USSD message exceeds maximum length of 182 characters');
    }

    $payload = [
      'phone'        => $phone,
      'service_code' => $serviceCode,
      'message'      => $message,
      'reference'    => $options['reference'] ?? null,
      'callback_url' => $options['callback_url'] ?? null,
      'metadata'     => $options['metadata'] ?? [],
    ];

    $response = $this->http()->post("{$this->baseUrl}/ussd/push", $payload);

    if ($response->failed()) {
      $this->handleError($response);
    }

    return $this->parseResponse($response);
  }

  /**
   * Retrieve the details and current status of a USSD session.
   *
   * @param  string  $sessionId  Session ID returned by push() or the webhook
   * @return array{data: array{session_id: string, status: string, input: string, created_at: string}}
   */
  public function session(string $sessionId): array
  {
    if (empty(trim($sessionId))) {
      throw new ValidationException('Session ID is required');
    }

    $response = $this->http()->get("{$this->baseUrl}/ussd/sessions/{$sessionId}");

    if ($response->failed()) {
      $this->handleError($response);
    }

    return $this->parseResponse($response);
  }

  /**
   * Send a response within an active interactive USSD session.
   *
   * @param  string  $sessionId   Active session ID
   * @param  string  $message     Text to send to the subscriber (max 182 characters)
   * @param  bool    $endSession  Set true to terminate the session after this response
   * @return array{data: array{session_id: string, status: string}}
   */
  public function respond(string $sessionId, string $message, bool $endSession = false): array
  {
    if (empty(trim($sessionId))) {
      throw new ValidationException('Session ID is required');
    }

    if (empty(trim($message))) {
      throw new ValidationException('Response message is required');
    }

    if (mb_strlen($message) > 182) {
      throw new ValidationException('USSD response exceeds maximum length of 182 characters');
    }

    $payload = [
      'message'     => $message,
      'end_session' => $endSession,
    ];

    $response = $this->http()->post("{$this->baseUrl}/ussd/sessions/{$sessionId}/respond", $payload);

    if ($response->failed()) {
      $this->handleError($response);
    }

    return $this->parseResponse($response);
  }
}
