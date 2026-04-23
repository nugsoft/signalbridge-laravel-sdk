# Changelog

All notable changes to `signalbridge-laravel-sdk` will be documented in this file.

## [2.0.0] - 2026-04-23

### Added
- **Multi-channel architecture** — SMS, WhatsApp, and Mobile Money through a single SDK
- **`SignalBridgeClient::sms()`** — returns a lazy-loaded `SmsClient` channel instance
  - `send(string $recipient, string $message, array $options)` — send a single SMS
  - `sendBatch(array $messages, array $options)` — send multiple SMS in one request
  - `calculateSegments(string $message): int` — calculate segment count
  - `estimateCost(string $message, float $segmentPrice): float` — estimate total cost
- **`SignalBridgeClient::whatsapp()`** — returns a lazy-loaded `WhatsAppClient` channel instance
  - `send(string $recipient, string $message, array $options)` — send a plain WhatsApp message (max 4096 chars)
  - `sendTemplate(string $recipient, string $templateName, array $components, array $options)` — send a Meta-approved template message
- **`SignalBridgeClient::mobileMoney()`** — returns a lazy-loaded `MobileMoneyClient` channel instance
  - `initiate(string $phone, float $amount, string $currency, array $options)` — initiate a mobile money collection (request-to-pay)
  - `verify(string $transactionId)` — poll the status of a transaction
  - `disburse(string $phone, float $amount, string $currency, array $options)` — send money to a phone number
- **`SignalBridgeClient::ussd()`** — returns a lazy-loaded `UssdClient` channel instance (scaffolded, awaiting server-side USSD engine)
  - `push(string $phone, string $serviceCode, string $message, array $options)` — push a USSD prompt to a subscriber
  - `session(string $sessionId)` — retrieve session status and subscriber input
  - `respond(string $sessionId, string $message, bool $endSession)` — reply within an active session
- **`BaseChannelClient`** abstract class (`src/Channels/BaseChannelClient.php`) — shared HTTP client, authentication, error handling, and response parsing for all channel clients
- **`SignalBridgeClientInterface`** updated — new `sms()`, `whatsapp()`, `mobileMoney()` method contracts
- **`SignalBridge` Facade** updated — new `@method` annotations for all channel methods

### Changed
- Laravel 13 officially supported (composer constraint already included `^13.0`; updated README and docs)

### Backward Compatible
- All v1.x methods (`sendSms()`, `sendBatch()`, `getBalance()`, `getBalanceSummary()`, `getTransactions()`, `listWebhooks()`, `createWebhook()`, etc.) remain on `SignalBridgeClient` unchanged — no breaking changes for existing code

## [1.1.0] - 2026-04-22

### Added
- **Webhook management** — full CRUD for outbound client webhooks:
  - `listWebhooks()` — list all registered webhooks
  - `createWebhook($url, $events, $isActive)` — register a new webhook with event subscriptions (`message.sent`, `message.delivered`, `message.failed`, `message.permanently_failed`, `*`)
  - `getWebhook($id)` — retrieve a specific webhook
  - `updateWebhook($id, $data)` — update URL, events, or active state
  - `deleteWebhook($id)` — remove a webhook
  - `regenerateWebhookSecret($id)` — rotate the HMAC signing secret (returned once)
- **Export methods** — download data as CSV strings:
  - `exportMessages(array $filters)` — export message history (filterable by `start_date`, `end_date`, `status`)
  - `exportTransactions(array $filters)` — export transaction history (filterable by `start_date`, `end_date`, `type`, `currency`)
- **`InsufficientPermissionsException`** — new typed exception for role-based 403 responses (e.g. when a `viewer` role user attempts to send SMS). Previously all 403s raised `NoClientException`.

### Changed
- `handleError()` now distinguishes role/permission 403s (`InsufficientPermissionsException`) from missing-client 403s (`NoClientException`) based on the API response message

## [1.0.0] - 2027-11-26

### Added
- Initial release
- Send single SMS messages
- Send batch SMS (up to 100 messages)
- Balance management (check balance, get summary, view transactions)
- Token management (list tokens, revoke current token)
- Scheduled message support
- Segment calculation (GSM 7-bit vs Unicode detection)
- Cost estimation
- Laravel Facade support (`SignalBridge::sendSms()`)
- Dependency injection support
- Custom typed exceptions:
  - `InsufficientBalanceException`
  - `ValidationException`
  - `NoClientException`
  - `ServiceUnavailableException`
  - `SignalBridgeException`
- Configuration file with environment variable support
- Service Provider with auto-discovery
- Comprehensive documentation with real-world examples
- Support for Laravel 10, 11, and 12
- Support for PHP 8.1, 8.2, 8.3, and 8.4

### Features
- Unified API for multiple SMS vendors (SpeedaMobile, Africa's Talking)
- Automatic segment calculation and cost estimation
- Metadata support for audit trails
- Test mode for development
- Custom sender ID support
- Scheduled message delivery
- Batch processing with detailed results
- Balance tracking and transaction history
- Comprehensive error handling
