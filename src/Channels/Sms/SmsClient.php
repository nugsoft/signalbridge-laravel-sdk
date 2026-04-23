<?php

namespace Nugsoft\SignalBridge\Channels\Sms;

use Nugsoft\SignalBridge\Channels\BaseChannelClient;
use Nugsoft\SignalBridge\Exceptions\ValidationException;

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
   * Calculate the number of message segments for billing purposes.
   *
   * GSM 7-bit: 160 chars per segment (153 for multi-part)
   * Unicode:   70 chars per segment (67 for multi-part)
   *
   * @param  string  $message  Message text
   * @return int Number of segments
   */
  public function calculateSegments(string $message): int
  {
    $length = mb_strlen($message);
    $isUnicode = !$this->isGsm7Bit($message);

    if ($isUnicode) {
      return $length <= 70 ? 1 : (int) ceil($length / 67);
    }

    return $length <= 160 ? 1 : (int) ceil($length / 153);
  }

  /**
   * Estimate the cost of sending a message.
   *
   * @param  string  $message       Message text
   * @param  float   $segmentPrice  Price per segment (from getBalance())
   * @return float Estimated total cost
   */
  public function estimateCost(string $message, float $segmentPrice): float
  {
    return $this->calculateSegments($message) * $segmentPrice;
  }

  /**
   * Check if all characters in a string fall within the GSM 7-bit alphabet.
   */
  private function isGsm7Bit(string $text): bool
  {
    static $lookup = null;

    if ($lookup === null) {
      $chars = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";
      $lookup = array_flip(mb_str_split($chars));
    }

    foreach (mb_str_split($text) as $char) {
      if (!isset($lookup[$char])) {
        return false;
      }
    }

    return true;
  }
}
