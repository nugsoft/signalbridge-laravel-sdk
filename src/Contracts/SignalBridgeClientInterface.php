<?php

namespace Nugsoft\SignalBridge\Contracts;

use Nugsoft\SignalBridge\Channels\MobileMoney\MobileMoneyClient;
use Nugsoft\SignalBridge\Channels\Sms\SmsClient;
use Nugsoft\SignalBridge\Channels\Ussd\UssdClient;
use Nugsoft\SignalBridge\Channels\WhatsApp\WhatsAppClient;

interface SignalBridgeClientInterface
{
  // Channel accessors
  public function sms(): SmsClient;
  public function whatsapp(): WhatsAppClient;
  public function mobileMoney(): MobileMoneyClient;
  public function ussd(): UssdClient; // Planned: USSD engine coming soon

  // SMS (backward compat)
  public function sendSms(string $recipient, string $message, array $options = []): array;
  public function sendBatch(array $messages, array $options = []): array;

  // Account-level
  public function getBalance(string $currency = 'UGX'): array;
  public function getBalanceSummary(): array;
  public function getTransactions(array $filters = []): array;
  public function getTokens(): array;
  public function revokeCurrentToken(): array;

  // Webhooks
  public function listWebhooks(): array;
  public function createWebhook(string $url, array $events = ['*'], bool $isActive = true): array;
  public function getWebhook(int $webhookId): array;
  public function updateWebhook(int $webhookId, array $data): array;
  public function deleteWebhook(int $webhookId): array;
  public function regenerateWebhookSecret(int $webhookId): array;

  // Exports
  public function exportMessages(array $filters = []): string;
  public function exportTransactions(array $filters = []): string;

  // Utilities
  public function calculateSegments(string $message): int;
  public function estimateCost(string $message, float $segmentPrice): float;
}
