<?php

namespace Nugsoft\SignalBridge;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Nugsoft\SignalBridge\Exceptions\InsufficientBalanceException;
use Nugsoft\SignalBridge\Exceptions\NoClientException;
use Nugsoft\SignalBridge\Exceptions\ServiceUnavailableException;
use Nugsoft\SignalBridge\Exceptions\SignalBridgeException;
use Nugsoft\SignalBridge\Exceptions\ValidationException;

class SignalBridgeClient
{
  private string $baseUrl;
  private string $token;
  private int $timeout;

  public function __construct(?string $token = null, ?string $baseUrl = null, int $timeout = 30)
  {
    $this->baseUrl = $baseUrl ?? config('signalbridge.url', 'https://signal-bridge.nugsoftstagging.com/api');
    $this->token = $token ?? config('signalbridge.token');
    $this->timeout = $timeout;

    if (empty($this->token)) {
      throw new SignalBridgeException('SignalBridge API token is required. Set SIGNALBRIDGE_TOKEN in your .env file.');
    }
  }

  /**
   * Send an SMS message
   *
   * @param  string  $recipient  Phone number (e.g., '256700000000')
   * @param  string  $message  Message content (max 1000 chars)
   * @param  array  $options  Optional parameters
   * @return array Response data
   *
   * @throws SignalBridgeException
   */
  public function sendSms(string $recipient, string $message, array $options = []): array
  {
    $payload = array_merge([
      'recipient' => $recipient,
      'message' => $message,
      // 'sender_id' => $options['sender_id'] ?? config('app.name'),
      'metadata' => $options['metadata'] ?? [],
      'is_test' => $options['is_test'] ?? false,
    ], $options);

    $response = Http::withToken($this->token)
      ->timeout($this->timeout)
      ->post("{$this->baseUrl}/sms/send", $payload);

    if ($response->failed()) {
      $this->handleError($response);
    }

    return $response->json();
  }

  /**
   * Send batch SMS messages
   *
   * @param  array  $messages  Array of message objects
   * @param  array  $options  Optional parameters
   * @return array Response data
   *
   * @throws SignalBridgeException
   */
  public function sendBatch(array $messages, array $options = []): array
  {
    $payload = [
      'messages' => $messages,
      // 'sender_id' => $options['sender_id'] ?? config('app.name'),
      'is_test' => $options['is_test'] ?? false,
    ];

    $response = Http::withToken($this->token)
      ->timeout(60) // Longer timeout for batch operations
      ->post("{$this->baseUrl}/sms/send-batch", $payload);

    if ($response->failed()) {
      $this->handleError($response);
    }

    return $response->json();
  }

  /**
   * Get current balance for a currency
   *
   * @param  string  $currency  Currency code (default: UGX)
   * @return array Balance details
   *
   * @throws SignalBridgeException
   */
  public function getBalance(string $currency = 'UGX'): array
  {
    $response = Http::withToken($this->token)
      ->timeout($this->timeout)
      ->get("{$this->baseUrl}/balance", ['currency' => $currency]);

    if ($response->failed()) {
      $this->handleError($response);
    }

    return $response->json();
  }

  /**
   * Get balance summary with recent activity
   *
   * @return array Summary data
   *
   * @throws SignalBridgeException
   */
  public function getBalanceSummary(): array
  {
    $response = Http::withToken($this->token)
      ->timeout($this->timeout)
      ->get("{$this->baseUrl}/balance/summary");

    if ($response->failed()) {
      $this->handleError($response);
    }

    return $response->json();
  }

  /**
   * Get transaction history
   *
   * @param  array  $filters  Filter options (per_page, page, type, start_date, end_date)
   * @return array Paginated transactions
   *
   * @throws SignalBridgeException
   */
  public function getTransactions(array $filters = []): array
  {
    $response = Http::withToken($this->token)
      ->timeout($this->timeout)
      ->get("{$this->baseUrl}/balance/transactions", $filters);

    if ($response->failed()) {
      $this->handleError($response);
    }

    return $response->json();
  }

  /**
   * Get all API tokens
   *
   * @return array List of tokens
   *
   * @throws SignalBridgeException
   */
  public function getTokens(): array
  {
    $response = Http::withToken($this->token)
      ->timeout($this->timeout)
      ->get("{$this->baseUrl}/tokens");

    if ($response->failed()) {
      $this->handleError($response);
    }

    return $response->json();
  }

  /**
   * Revoke the current API token
   *
   * @return array Response data
   *
   * @throws SignalBridgeException
   */
  public function revokeCurrentToken(): array
  {
    $response = Http::withToken($this->token)
      ->timeout($this->timeout)
      ->delete("{$this->baseUrl}/tokens/current");

    if ($response->failed()) {
      $this->handleError($response);
    }

    return $response->json();
  }

  /**
   * Calculate approximate segments for a message
   *
   * @param  string  $message  Message content
   * @return int Number of segments
   */
  public function calculateSegments(string $message): int
  {
    $length = mb_strlen($message);
    $isUnicode = ! $this->isGsm7Bit($message);

    if ($isUnicode) {
      return $length <= 70 ? 1 : (int) ceil($length / 67);
    }

    return $length <= 160 ? 1 : (int) ceil($length / 153);
  }

  /**
   * Estimate cost for a message
   *
   * @param  string  $message  Message content
   * @param  float  $segmentPrice  Price per segment
   * @return float Estimated cost
   */
  public function estimateCost(string $message, float $segmentPrice): float
  {
    return $this->calculateSegments($message) * $segmentPrice;
  }

  /**
   * Check if message uses GSM 7-bit encoding
   *
   * @param  string  $text  Message text
   * @return bool
   */
  private function isGsm7Bit(string $text): bool
  {
    $gsm7BitChars = '@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !"#¤%&\'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà';

    for ($i = 0; $i < mb_strlen($text); $i++) {
      if (mb_strpos($gsm7BitChars, mb_substr($text, $i, 1)) === false) {
        return false;
      }
    }

    return true;
  }

  /**
   * Handle API errors
   *
   * @param  \Illuminate\Http\Client\Response  $response
   * @return void
   *
   * @throws SignalBridgeException
   */
  private function handleError($response): void
  {
    $status = $response->status();
    $data = $response->json();

    Log::error('SignalBridge API Error', [
      'status' => $status,
      'response' => $data,
    ]);

    $message = $data['message'] ?? 'Unknown error occurred';

    match ($status) {
      402 => throw new InsufficientBalanceException($message, $data['data'] ?? []),
      403 => throw new NoClientException($message),
      422 => throw new ValidationException($message, $data['errors'] ?? [], $data),
      503 => throw new ServiceUnavailableException($message),
      default => throw new SignalBridgeException($message, $status, $data),
    };
  }
}
