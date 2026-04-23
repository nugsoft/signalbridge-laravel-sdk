<?php

namespace Nugsoft\SignalBridge;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Nugsoft\SignalBridge\Channels\MobileMoney\MobileMoneyClient;
use Nugsoft\SignalBridge\Channels\Sms\SmsClient;
use Nugsoft\SignalBridge\Channels\Ussd\UssdClient;
use Nugsoft\SignalBridge\Channels\WhatsApp\WhatsAppClient;
use Nugsoft\SignalBridge\Contracts\SignalBridgeClientInterface;
use Nugsoft\SignalBridge\Exceptions\InsufficientBalanceException;
use Nugsoft\SignalBridge\Exceptions\InsufficientPermissionsException;
use Nugsoft\SignalBridge\Exceptions\NoClientException;
use Nugsoft\SignalBridge\Exceptions\RateLimitedException;
use Nugsoft\SignalBridge\Exceptions\ServiceUnavailableException;
use Nugsoft\SignalBridge\Exceptions\SignalBridgeException;
use Nugsoft\SignalBridge\Exceptions\UnauthorizedException;
use Nugsoft\SignalBridge\Exceptions\ValidationException;

class SignalBridgeClient implements SignalBridgeClientInterface
{
  private string $baseUrl;
  private string $token;
  private int $timeout;

  private ?SmsClient $smsChannel = null;
  private ?WhatsAppClient $whatsAppChannel = null;
  private ?MobileMoneyClient $mobileMoneyChannel = null;
  private ?UssdClient $ussdChannel = null;

  public function __construct(?string $token = null, ?string $baseUrl = null, int $timeout = 30)
  {
    $this->baseUrl = rtrim($baseUrl ?? config('signalbridge.url', 'http://signal-bridge.nugsoftapps.net/api'), '/');
    $this->token = $token ?? config('signalbridge.token', '');
    $this->timeout = $timeout;

    if (empty($this->token)) {
      throw new SignalBridgeException('SignalBridge API token is required. Set SIGNALBRIDGE_TOKEN in your .env file.');
    }
  }

  // -------------------------------------------------------------------------
  // Channel Accessors
  // -------------------------------------------------------------------------

  /**
   * Access the SMS channel client.
   *
   * @example SignalBridge::sms()->send('256700000000', 'Hello!')
   */
  public function sms(): SmsClient
  {
    return $this->smsChannel ??= new SmsClient($this->baseUrl, $this->token, $this->timeout);
  }

  /**
   * Access the WhatsApp channel client.
   *
   * @example SignalBridge::whatsapp()->send('256700000000', 'Hello!')
   * @example SignalBridge::whatsapp()->sendTemplate('256700000000', 'order_confirmation', [...])
   */
  public function whatsapp(): WhatsAppClient
  {
    return $this->whatsAppChannel ??= new WhatsAppClient($this->baseUrl, $this->token, $this->timeout);
  }

  /**
   * Access the Mobile Money channel client.
   *
   * @example SignalBridge::mobileMoney()->initiate('256700000000', 5000)
   * @example SignalBridge::mobileMoney()->disburse('256700000000', 50000)
   */
  public function mobileMoney(): MobileMoneyClient
  {
    return $this->mobileMoneyChannel ??= new MobileMoneyClient($this->baseUrl, $this->token, $this->timeout);
  }

  /**
   * Access the USSD channel client.
   *
   * @note USSD support is planned — available once the server-side USSD engine is released.
   * @example SignalBridge::ussd()->push('256700000000', '*123#', 'Enter PIN:')
   * @example SignalBridge::ussd()->session($sessionId)
   * @example SignalBridge::ussd()->respond($sessionId, 'Thank you!', endSession: true)
   */
  public function ussd(): UssdClient
  {
    return $this->ussdChannel ??= new UssdClient($this->baseUrl, $this->token, $this->timeout);
  }

  // -------------------------------------------------------------------------
  // SMS — kept for backward compatibility (proxies to SmsClient)
  // -------------------------------------------------------------------------

  /**
   * Send an SMS message
   *
   * @param  string  $recipient  Phone number (e.g., '256700000000')
   * @param  string  $message  Message content (max 1000 chars)
   * @param  array  $options  Optional parameters: metadata, is_test, sender_id, scheduled_at
   * @return array Response data
   *
   * @throws ValidationException
   * @throws InsufficientBalanceException
   * @throws UnauthorizedException
   * @throws RateLimitedException
   * @throws SignalBridgeException
   */
  public function sendSms(string $recipient, string $message, array $options = []): array
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
      'message' => $message,
      'metadata' => $options['metadata'] ?? [],
      'is_test' => $options['is_test'] ?? false,
    ];

    $senderId = $options['sender_id'] ?? config('signalbridge.default_sender_id');
    if (!empty($senderId)) {
      $payload['sender_id'] = $senderId;
    }

    if (isset($options['scheduled_at'])) {
      $payload['scheduled_at'] = $options['scheduled_at'];
    }

    $response = Http::withToken($this->token)
      ->timeout($this->timeout)
      ->post("{$this->baseUrl}/sms/send", $payload);

    if ($response->failed()) {
      $this->handleError($response);
    }

    return $this->parseResponse($response);
  }

  /**
   * Send batch SMS messages
   *
   * @param  array  $messages  Array of message objects, each with 'recipient' and 'message' keys
   * @param  array  $options  Optional parameters: is_test, sender_id
   * @return array Response data
   *
   * @throws ValidationException
   * @throws InsufficientBalanceException
   * @throws UnauthorizedException
   * @throws RateLimitedException
   * @throws SignalBridgeException
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
      'is_test' => $options['is_test'] ?? false,
    ];

    $senderId = $options['sender_id'] ?? config('signalbridge.default_sender_id');
    if (!empty($senderId)) {
      $payload['sender_id'] = $senderId;
    }

    $response = Http::withToken($this->token)
      ->timeout($this->timeout)
      ->post("{$this->baseUrl}/sms/send-batch", $payload);

    if ($response->failed()) {
      $this->handleError($response);
    }

    return $this->parseResponse($response);
  }

  /**
   * Get current balance for a currency
   *
   * @param  string  $currency  Currency code (default: UGX)
   * @return array Balance details
   *
   * @throws UnauthorizedException
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

    return $this->parseResponse($response);
  }

  /**
   * Get balance summary with recent activity
   *
   * @return array Summary data
   *
   * @throws UnauthorizedException
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

    return $this->parseResponse($response);
  }

  /**
   * Get transaction history
   *
   * @param  array  $filters  Filter options (per_page, page, type, start_date, end_date)
   * @return array Paginated transactions
   *
   * @throws UnauthorizedException
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

    return $this->parseResponse($response);
  }

  /**
   * Get all API tokens
   *
   * @return array List of tokens
   *
   * @throws UnauthorizedException
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

    return $this->parseResponse($response);
  }

  /**
   * List all outbound webhooks for the authenticated client
   *
   * @return array Webhook list
   *
   * @throws UnauthorizedException
   * @throws SignalBridgeException
   */
  public function listWebhooks(): array
  {
    $response = Http::withToken($this->token)
      ->timeout($this->timeout)
      ->get("{$this->baseUrl}/webhooks");

    if ($response->failed()) {
      $this->handleError($response);
    }

    return $this->parseResponse($response);
  }

  /**
   * Create a new outbound webhook
   *
   * @param  string  $url  The URL SignalBridge will POST events to
   * @param  array   $events  Event types to subscribe to (e.g. ['message.sent', 'message.delivered', 'message.failed', '*'])
   * @param  bool    $isActive  Whether the webhook is active immediately
   * @return array  Created webhook data including the one-time secret
   *
   * @throws ValidationException
   * @throws UnauthorizedException
   * @throws SignalBridgeException
   */
  public function createWebhook(string $url, array $events = ['*'], bool $isActive = true): array
  {
    $response = Http::withToken($this->token)
      ->timeout($this->timeout)
      ->post("{$this->baseUrl}/webhooks", [
        'url'       => $url,
        'events'    => $events,
        'is_active' => $isActive,
      ]);

    if ($response->failed()) {
      $this->handleError($response);
    }

    return $this->parseResponse($response);
  }

  /**
   * Get a specific webhook by ID
   *
   * @param  int  $webhookId
   * @return array Webhook data
   *
   * @throws UnauthorizedException
   * @throws SignalBridgeException
   */
  public function getWebhook(int $webhookId): array
  {
    $response = Http::withToken($this->token)
      ->timeout($this->timeout)
      ->get("{$this->baseUrl}/webhooks/{$webhookId}");

    if ($response->failed()) {
      $this->handleError($response);
    }

    return $this->parseResponse($response);
  }

  /**
   * Update an existing webhook
   *
   * @param  int    $webhookId
   * @param  array  $data  Fields to update: url, events, is_active
   * @return array Updated webhook data
   *
   * @throws ValidationException
   * @throws UnauthorizedException
   * @throws SignalBridgeException
   */
  public function updateWebhook(int $webhookId, array $data): array
  {
    $response = Http::withToken($this->token)
      ->timeout($this->timeout)
      ->put("{$this->baseUrl}/webhooks/{$webhookId}", $data);

    if ($response->failed()) {
      $this->handleError($response);
    }

    return $this->parseResponse($response);
  }

  /**
   * Delete a webhook
   *
   * @param  int  $webhookId
   * @return array Confirmation response
   *
   * @throws UnauthorizedException
   * @throws SignalBridgeException
   */
  public function deleteWebhook(int $webhookId): array
  {
    $response = Http::withToken($this->token)
      ->timeout($this->timeout)
      ->delete("{$this->baseUrl}/webhooks/{$webhookId}");

    if ($response->failed()) {
      $this->handleError($response);
    }

    return $this->parseResponse($response);
  }

  /**
   * Regenerate the signing secret for a webhook
   *
   * The new secret is returned only once in the response. Store it securely.
   *
   * @param  int  $webhookId
   * @return array Response containing the new secret
   *
   * @throws UnauthorizedException
   * @throws SignalBridgeException
   */
  public function regenerateWebhookSecret(int $webhookId): array
  {
    $response = Http::withToken($this->token)
      ->timeout($this->timeout)
      ->post("{$this->baseUrl}/webhooks/{$webhookId}/regenerate-secret");

    if ($response->failed()) {
      $this->handleError($response);
    }

    return $this->parseResponse($response);
  }

  /**
   * Export messages as a CSV string
   *
   * @param  array  $filters  Optional filters: start_date, end_date, status
   * @return string  Raw CSV content
   *
   * @throws UnauthorizedException
   * @throws SignalBridgeException
   */
  public function exportMessages(array $filters = []): string
  {
    $response = Http::withToken($this->token)
      ->timeout(120) // exports can be large
      ->get("{$this->baseUrl}/export/messages", $filters);

    if ($response->failed()) {
      $this->handleError($response);
    }

    return $response->body();
  }

  /**
   * Export balance transactions as a CSV string
   *
   * @param  array  $filters  Optional filters: start_date, end_date, type, currency
   * @return string  Raw CSV content
   *
   * @throws UnauthorizedException
   * @throws SignalBridgeException
   */
  public function exportTransactions(array $filters = []): string
  {
    $response = Http::withToken($this->token)
      ->timeout(120)
      ->get("{$this->baseUrl}/export/transactions", $filters);

    if ($response->failed()) {
      $this->handleError($response);
    }

    return $response->body();
  }

  /**
   * Revoke the current API token
   *
   * @return array Response data
   *
   * @throws UnauthorizedException
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

    return $this->parseResponse($response);
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
    $isUnicode = !$this->isGsm7Bit($message);

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
   * Check if message uses GSM 7-bit encoding using an O(n) lookup table.
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

  /**
   * Parse a successful JSON response, throwing if the body is not a JSON object/array.
   *
   * @throws SignalBridgeException
   */
  private function parseResponse(Response $response): array
  {
    $data = $response->json();

    if (!is_array($data)) {
      throw new SignalBridgeException('Unexpected API response format');
    }

    return $data;
  }

  /**
   * Handle a failed HTTP response by mapping status codes to typed exceptions.
   *
   * @throws SignalBridgeException
   */
  private function handleError(Response $response): never
  {
    $status = $response->status();

    $data = $response->json();
    if (!is_array($data)) {
      $data = [];
    }

    if (config('signalbridge.logging', true)) {
      Log::error('SignalBridge API Error', [
        'status' => $status,
        'message' => $data['message'] ?? 'Unknown error',
        'error_code' => $data['code'] ?? null,
      ]);
    }

    $message = $data['message'] ?? 'Unknown error occurred';

    match ($status) {
      401 => throw new UnauthorizedException($message),
      402 => throw new InsufficientBalanceException($message, $data['data'] ?? []),
      403 => str_contains(strtolower($message), 'permission') || str_contains(strtolower($message), 'role')
        ? throw new InsufficientPermissionsException($message)
        : throw new NoClientException($message),
      404 => throw new SignalBridgeException('API endpoint not found. Verify SIGNALBRIDGE_URL configuration.', $status, $data),
      422 => throw new ValidationException($message, $data['errors'] ?? [], $data),
      429 => throw new RateLimitedException($message),
      500 => throw new SignalBridgeException('SignalBridge server error. Please try again later.', $status, $data),
      503 => throw new ServiceUnavailableException($message),
      default => throw new SignalBridgeException($message, $status, $data),
    };
  }
}
