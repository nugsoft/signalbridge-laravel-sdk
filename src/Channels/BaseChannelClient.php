<?php

namespace Nugsoft\SignalBridge\Channels;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Nugsoft\SignalBridge\Exceptions\InsufficientBalanceException;
use Nugsoft\SignalBridge\Exceptions\InsufficientPermissionsException;
use Nugsoft\SignalBridge\Exceptions\NoClientException;
use Nugsoft\SignalBridge\Exceptions\RateLimitedException;
use Nugsoft\SignalBridge\Exceptions\ServiceUnavailableException;
use Nugsoft\SignalBridge\Exceptions\SignalBridgeException;
use Nugsoft\SignalBridge\Exceptions\UnauthorizedException;
use Nugsoft\SignalBridge\Exceptions\ValidationException;

abstract class BaseChannelClient
{
  protected string $baseUrl;
  protected string $token;
  protected int $timeout;

  public function __construct(string $baseUrl, string $token, int $timeout = 30)
  {
    $this->baseUrl = $baseUrl;
    $this->token = $token;
    $this->timeout = $timeout;
  }

  protected function http(): PendingRequest
  {
    return Http::withToken($this->token)->timeout($this->timeout);
  }

  protected function parseResponse(Response $response): array
  {
    $data = $response->json();

    if (!is_array($data)) {
      throw new SignalBridgeException('Unexpected API response format');
    }

    return $data;
  }

  protected function handleError(Response $response): never
  {
    $status = $response->status();

    $data = $response->json();
    if (!is_array($data)) {
      $data = [];
    }

    if (config('signalbridge.logging', true)) {
      Log::error('SignalBridge API Error', [
        'status'     => $status,
        'message'    => $data['message'] ?? 'Unknown error',
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
