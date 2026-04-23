<?php

namespace Nugsoft\SignalBridge\Channels\WhatsApp;

use Nugsoft\SignalBridge\Channels\BaseChannelClient;
use Nugsoft\SignalBridge\Exceptions\ValidationException;

class WhatsAppClient extends BaseChannelClient
{
  /**
   * Send a plain-text WhatsApp message.
   *
   * @param  string  $recipient  Phone number in E.164 format (e.g. '256700000000')
   * @param  string  $message    Message text (max 4096 characters)
   * @param  array   $options    Optional: metadata, is_test
   */
  public function send(string $recipient, string $message, array $options = []): array
  {
    if (empty(trim($recipient))) {
      throw new ValidationException('Recipient phone number is required');
    }

    if (empty(trim($message))) {
      throw new ValidationException('Message content is required');
    }

    if (mb_strlen($message) > 4096) {
      throw new ValidationException('WhatsApp message exceeds maximum length of 4096 characters');
    }

    $payload = [
      'recipient' => $recipient,
      'message'   => $message,
      'metadata'  => $options['metadata'] ?? [],
      'is_test'   => $options['is_test'] ?? false,
    ];

    $response = $this->http()->post("{$this->baseUrl}/whatsapp/send", $payload);

    if ($response->failed()) {
      $this->handleError($response);
    }

    return $this->parseResponse($response);
  }

  /**
   * Send a WhatsApp template message (pre-approved Meta Business template).
   *
   * Templates must be approved in Meta Business Manager before use.
   * Components map to the template's variable placeholders.
   *
   * @param  string  $recipient     Phone number in E.164 format
   * @param  string  $templateName  The approved template name (e.g. 'order_confirmation')
   * @param  array   $components    Variable components for template placeholders
   * @param  array   $options       Optional: language (default 'en_US'), metadata, is_test
   *
   * @example
   * SignalBridge::whatsapp()->sendTemplate(
   *     recipient: '256700000000',
   *     templateName: 'order_confirmation',
   *     components: [
   *         ['type' => 'body', 'parameters' => [
   *             ['type' => 'text', 'text' => 'John'],
   *             ['type' => 'text', 'text' => '#ORD-9821'],
   *         ]]
   *     ]
   * );
   */
  public function sendTemplate(string $recipient, string $templateName, array $components = [], array $options = []): array
  {
    if (empty(trim($recipient))) {
      throw new ValidationException('Recipient phone number is required');
    }

    if (empty(trim($templateName))) {
      throw new ValidationException('Template name is required');
    }

    $payload = [
      'recipient'  => $recipient,
      'template'   => $templateName,
      'components' => $components,
      'language'   => $options['language'] ?? 'en_US',
      'metadata'   => $options['metadata'] ?? [],
      'is_test'    => $options['is_test'] ?? false,
    ];

    $response = $this->http()->post("{$this->baseUrl}/whatsapp/send", $payload);

    if ($response->failed()) {
      $this->handleError($response);
    }

    return $this->parseResponse($response);
  }
}
