<?php

namespace Nugsoft\SignalBridge\Channels\Sms;

use Nugsoft\SignalBridge\Channels\BaseChannelClient;
use Nugsoft\SignalBridge\Exceptions\ValidationException;
use Nugsoft\SignalBridge\Support\MessageSegments;

class SmsClient extends BaseChannelClient
{
  /**
   * Send a single SMS message.
   *
   * @param  string  $recipient  Phone number in E.164 format (e.g. '256700000000')
   * @param  string  $message    Message text (max 1000 characters)
   * @param  array   $options    Optional: metadata, is_test, sender_id, scheduled_at
   */
  public function send(string $recipient, string $message, array $options = []): array
  {
    if (empty(trim($recipient))) {
      throw new ValidationException('Recipient phone number is required');
    }

    if (empty(trim($message))) {
      throw new ValidationException('Message content is required');
    }

    if (mb_strlen($message) > 1000) {
      throw new ValidationException('Message exceeds maximum length of 1000 characters');
    }

    $payload = [
      'recipient' => $recipient,
      'message'   => $message,
      'metadata'  => $options['metadata'] ?? [],
      'is_test'   => $options['is_test'] ?? false,
    ];

    $senderId = $options['sender_id'] ?? config('signalbridge.default_sender_id');
    if (!empty($senderId)) {
      $payload['sender_id'] = $senderId;
    }

    if (isset($options['scheduled_at'])) {
      $payload['scheduled_at'] = $options['scheduled_at'];
    }

    $response = $this->http()->post("{$this->baseUrl}/sms/send", $payload);

    if ($response->failed()) {
      $this->handleError($response);
    }

    return $this->parseResponse($response);
  }

  /**
   * Send multiple SMS messages in a single batch (up to 100 messages).
   *
   * @param  array  $messages  Array of ['recipient' => '...', 'message' => '...', 'metadata' => [...]]
   * @param  array  $options   Optional: is_test, sender_id
   */
  public function sendBatch(array $messages, array $options = []): array
  {
    if (empty($messages)) {
      throw new ValidationException('Messages array cannot be empty');
    }

    foreach ($messages as $index => $msg) {
      if (!is_array($msg)) {
        throw new ValidationException("Message at index {$index} must be an array");
      }
      if (empty($msg['recipient'] ?? '')) {
        throw new ValidationException("Message at index {$index} is missing 'recipient'");
      }
      if (empty($msg['message'] ?? '')) {
        throw new ValidationException("Message at index {$index} is missing 'message'");
      }
    }

    $payload = [
      'messages' => $messages,
      'is_test'  => $options['is_test'] ?? false,
    ];

    $senderId = $options['sender_id'] ?? config('signalbridge.default_sender_id');
    if (!empty($senderId)) {
      $payload['sender_id'] = $senderId;
    }

    $response = $this->http()->post("{$this->baseUrl}/sms/send-batch", $payload);

    if ($response->failed()) {
      $this->handleError($response);
    }

    return $this->parseResponse($response);
  }

  /**
   * Look up one message's delivery status.
   *
   * @param  int   $messageId  The id returned by send()
   * @param  bool  $refresh    Ask the vendor live instead of returning the
   *                           stored status. Rate limited, and rarely needed —
   *                           the gateway polls vendors in the background.
   */
  public function status(int $messageId, bool $refresh = false): array
  {
    $response = $this->http()->get(
      "{$this->baseUrl}/sms/messages/{$messageId}",
      $refresh ? ['refresh' => 1] : []
    );

    if ($response->failed()) {
      $this->handleError($response);
    }

    return $this->parseResponse($response);
  }

  /**
   * List messages with their delivery status.
   *
   * Pass 'ids' to follow up a batch — the ids come back from sendBatch(). The
   * 'summary' key in the response counts the whole filtered set, not just the
   * current page, so one call answers "how did that batch go".
   *
   * @param  array  $filters  ids, status, recipient, channel, start_date,
   *                          end_date, per_page, page
   */
  public function messages(array $filters = []): array
  {
    if (isset($filters['ids']) && is_array($filters['ids'])) {
      $filters['ids'] = implode(',', $filters['ids']);
    }

    $response = $this->http()->get("{$this->baseUrl}/sms/messages", $filters);

    if ($response->failed()) {
      $this->handleError($response);
    }

    return $this->parseResponse($response);
  }

  /**
   * Calculate the number of message segments for billing purposes.
   *
   * Delegates to MessageSegments so this and the gateway cannot disagree.
   */
  public function calculateSegments(string $message): int
  {
    return MessageSegments::count($message);
  }

  /**
   * Estimate the cost of sending a message.
   *
   * @param  float  $segmentPrice  Price per segment, from getBalance()
   */
  public function estimateCost(string $message, float $segmentPrice): float
  {
    return MessageSegments::estimateCost($message, $segmentPrice);
  }
}
