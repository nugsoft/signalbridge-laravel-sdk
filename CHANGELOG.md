# Changelog

All notable changes to `signalbridge-laravel-sdk` will be documented in this file.

## [Unreleased]

### Fixed
- **`estimateCost()` over-quoted templated messages.** The GSM alphabet was
  missing the escape-table characters `^ { } \ [ ] ~ | €`, so any message
  containing a placeholder such as `{name}` was treated as Unicode and billed at
  67 characters per segment instead of 153. A 200-character templated message
  was quoted at three segments where the gateway charges two.
- Segment counting now lives in one place (`Support\MessageSegments`) rather
  than being duplicated in `SignalBridgeClient` and `SmsClient`, which is how the
  two drifted apart in the first place. It mirrors the gateway's own calculation
  and is covered by the same test cases on both sides.
- Unicode length is counted in UTF-16 code units, so only characters outside the
  Basic Multilingual Plane take two. Three-byte sequences such as CJK were
  previously counted as two.

### Added
- **`SmsClient::status(int $messageId, bool $refresh = false)`** — read one
  message's delivery status. `$refresh` asks the vendor live.
- **`SmsClient::messages(array $filters = [])`** — list messages with a status
  breakdown. Pass `ids` to reconcile a batch in a single call; `summary` counts
  the whole filtered set rather than the current page.
- **`SignalBridgeClient::getMessageStatus()` / `getMessages()`** — the same, on
  the main client.
- **`Support\WebhookSignature`** — verify inbound webhooks. `verifyRequest()`
  checks the raw body with `hash_equals`, which is the part that is easy to get
  wrong by hand and fails silently when you do.
- **`SignalBridgeClient::verifyWebhookSignature()`** — convenience wrapper.
- A test suite. The package previously declared a `Tests\` autoload namespace
  and dev dependencies on phpunit, testbench and mockery, but shipped no tests.
- **Agent guidance at `resources/boost/guidelines/core.md`.** Laravel Boost
  discovers guidelines shipped by packages at that path and merges them into the
  consuming project's `CLAUDE.md`, `.github/copilot-instructions.md` and
  `.junie/guidelines.md`, so an agent working in a project that installs this SDK
  knows the rules that cost money to get wrong — chiefly that an unmocked test
  sends a real SMS. `AGENTS.md` points at the same file rather than copying it.

### Changed
- **`MobileMoneyClient::disburse()` now throws `ServiceUnavailableException`
  immediately.** The gateway exposes no `/mobile-money/disburse` endpoint, so
  the call used to 404 and be reported as "API endpoint not found. Verify
  SIGNALBRIDGE_URL configuration" — which points at the wrong problem entirely.
  Collecting payments with `initiate()` is unaffected.
- README no longer lists `payment.completed` and `payment.failed` as webhook
  events. The gateway rejects them with a 422 and has never dispatched them; the
  documented example would have failed.
- `createWebhook()`'s `$isActive` argument now takes effect. The gateway ignored
  `is_active` on create, so asking for a paused webhook produced a live one.
  Requires a gateway with that fix deployed.

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
