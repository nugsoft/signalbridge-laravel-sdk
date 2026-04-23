# SignalBridge Laravel SDK

[![Latest Version on Packagist](https://img.shields.io/packagist/v/nugsoft/signalbridge-laravel-sdk.svg?style=flat-square)](https://packagist.org/packages/nugsoft/signalbridge-laravel-sdk)
[![Total Downloads](https://img.shields.io/packagist/dt/nugsoft/signalbridge-laravel-sdk.svg?style=flat-square)](https://packagist.org/packages/nugsoft/signalbridge-laravel-sdk)

Official Laravel SDK for [SignalBridge](https://signal-bridge.nugsoftapps.net) — a unified multi-channel communication and payment gateway.

Send SMS, WhatsApp messages, and initiate Mobile Money transactions through a single, clean Laravel API. Built by Nugsoft.

## Channels

| Channel | Status | Methods |
|---|---|---|
| **SMS** | ✅ Available | `send()`, `sendBatch()`, `calculateSegments()`, `estimateCost()` |
| **WhatsApp** | ✅ Available | `send()`, `sendTemplate()` |
| **Mobile Money** | ✅ Available | `initiate()`, `verify()`, `disburse()` |
| **USSD** | 🔜 Planned | `push()`, `session()`, `respond()` |

## Features

- **Multi-channel API** — SMS, WhatsApp, Mobile Money, and USSD (planned) through one SDK
- **Channel-fluent interface** — `SignalBridge::sms()->send(...)`, `SignalBridge::whatsapp()->sendTemplate(...)`
- **Backward compatible** — existing `SignalBridge::sendSms()` calls still work
- **Balance & transaction management** — account-level operations on the main client
- **Webhook management** — full CRUD for outbound event webhooks
- **Export** — download messages and transactions as CSV
- **Typed exceptions** — specific exception classes for each error type
- **Facade + DI support** — use either style
- **Laravel 10, 11, 12, 13** — tested on all current versions
- **PHP 8.1+**

## Requirements

- PHP 8.1 or higher
- Laravel 10.0, 11.0, 12.0, or 13.0
- Guzzle HTTP 7.0+

## Installation

```bash
composer require nugsoft/signalbridge-laravel-sdk
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag=signalbridge-config
```

## Configuration

Add to your `.env`:

```env
SIGNALBRIDGE_TOKEN=your_api_token_here
SIGNALBRIDGE_URL=https://signal-bridge.nugsoftapps.net/api
```

**Getting your token:**

```bash
curl -X POST https://signal-bridge.nugsoftapps.net/api/tokens \
  -H "Content-Type: application/json" \
  -d '{"email": "you@example.com", "password": "your-password", "expires_in_days": 365}'
```

> You can also generate tokens from the SignalBridge dashboard under **Settings → API Tokens**.

---

## Usage

### Quick Start

```php
use Nugsoft\SignalBridge\Facades\SignalBridge;

// SMS
SignalBridge::sms()->send('256700000000', 'Hello from SignalBridge!');

// WhatsApp
SignalBridge::whatsapp()->send('256700000000', 'Hello on WhatsApp!');

// Mobile Money — collect payment
SignalBridge::mobileMoney()->initiate('256700000000', 5000);

// Mobile Money — send payout
SignalBridge::mobileMoney()->disburse('256700000000', 50000);
```

### Dependency Injection

```php
use Nugsoft\SignalBridge\SignalBridgeClient;

class NotificationService
{
    public function __construct(private SignalBridgeClient $signalBridge) {}

    public function sendWelcome(string $phone, string $name): void
    {
        $this->signalBridge->sms()->send($phone, "Welcome {$name}!");
    }
}
```

---

## SMS

### Send a Single SMS

```php
$result = SignalBridge::sms()->send(
    recipient: '256700000000',
    message: 'Your OTP is 123456',
    options: [
        'sender_id'    => 'MyApp',           // Optional
        'metadata'     => ['user_id' => 42], // Optional: stored for your records
        'is_test'      => false,             // Optional: test mode (no charge)
        'scheduled_at' => '2026-06-01T09:00:00Z', // Optional: ISO 8601
    ]
);
// $result['data']['message_id'], $result['data']['cost'], $result['data']['status']
```

### Send a Batch of SMS

```php
$result = SignalBridge::sms()->sendBatch(
    messages: [
        ['recipient' => '256700000000', 'message' => 'Hi Alice!', 'metadata' => ['user_id' => 1]],
        ['recipient' => '256700000001', 'message' => 'Hi Bob!',   'metadata' => ['user_id' => 2]],
    ],
    options: ['is_test' => false]
);
// $result['data']['successful'], $result['data']['failed']
```

### Segment Calculation & Cost Estimation

```php
$sms = SignalBridge::sms();

$segments = $sms->calculateSegments('Hello World'); // 1
$cost     = $sms->estimateCost('Hello World', segmentPrice: 1.00); // 1.00
```

**Encoding rules:**
- GSM 7-bit (standard): 160 chars = 1 segment, 153 chars/segment thereafter
- Unicode (emoji, Arabic, Chinese…): 70 chars = 1 segment, 67 chars/segment thereafter

---

## WhatsApp

### Send a Plain Message

```php
SignalBridge::whatsapp()->send(
    recipient: '256700000000',
    message: 'Your order #1234 has been shipped.',
    options: ['metadata' => ['order_id' => 1234]]
);
```

### Send a Template Message

Templates must be pre-approved in [Meta Business Manager](https://business.facebook.com).

```php
SignalBridge::whatsapp()->sendTemplate(
    recipient: '256700000000',
    templateName: 'order_confirmation',
    components: [
        [
            'type' => 'body',
            'parameters' => [
                ['type' => 'text', 'text' => 'Alice'],
                ['type' => 'text', 'text' => '#ORD-9821'],
                ['type' => 'text', 'text' => 'UGX 45,000'],
            ],
        ],
    ],
    options: ['language' => 'en_US']
);
```

---

## Mobile Money

### Collect a Payment (Request-to-Pay)

```php
$tx = SignalBridge::mobileMoney()->initiate(
    phone: '256700000000',
    amount: 15000,
    currency: 'UGX',
    options: [
        'reference'    => 'INV-2026-001',
        'description'  => 'Invoice payment',
        'callback_url' => 'https://yourapp.com/webhooks/momo',
        'metadata'     => ['invoice_id' => 101],
    ]
);

$transactionId = $tx['data']['transaction_id'];
$status        = $tx['data']['status']; // 'pending'
```

### Poll Transaction Status

```php
$result = SignalBridge::mobileMoney()->verify('txn-uuid-here');
// $result['data']['status'] — 'pending' | 'completed' | 'failed'
```

> Prefer webhooks over polling. Register a `callback_url` in `initiate()` or configure a webhook via `createWebhook()`.

### Disburse (Send Money)

```php
SignalBridge::mobileMoney()->disburse(
    phone: '256700000000',
    amount: 50000,
    currency: 'UGX',
    options: [
        'reference'   => 'SALARY-APR-2026',
        'description' => 'April salary',
    ]
);
```

---

## Account Operations

These methods are on the main `SignalBridgeClient` and apply across all channels.

### Balance

```php
$balance = SignalBridge::getBalance('UGX');
// ['balance' => 996.0, 'currency' => 'UGX', 'segment_price' => 1.0, ...]

$summary = SignalBridge::getBalanceSummary();
```

### Transaction History

```php
$transactions = SignalBridge::getTransactions([
    'type'       => 'debit',        // 'credit' | 'debit'
    'start_date' => '2026-01-01',
    'end_date'   => '2026-04-30',
    'per_page'   => 50,
    'page'       => 1,
]);
```

### Export Data as CSV

```php
// Save messages to a file
$csv = SignalBridge::exportMessages(['start_date' => '2026-04-01']);
Storage::put('exports/messages.csv', $csv);

// Save transactions to a file
$csv = SignalBridge::exportTransactions(['type' => 'debit']);
Storage::put('exports/transactions.csv', $csv);
```

---

## Webhook Management

SignalBridge can POST events to your application when message or payment statuses change.

```php
// Register a webhook
$webhook = SignalBridge::createWebhook(
    url: 'https://yourapp.com/webhooks/signalbridge',
    events: ['message.delivered', 'message.failed', 'payment.completed'],
    isActive: true
);
$secret = $webhook['data']['secret']; // Store this — shown only once

// List, update, delete
$list = SignalBridge::listWebhooks();
SignalBridge::updateWebhook($webhookId, ['is_active' => false]);
SignalBridge::deleteWebhook($webhookId);

// Rotate secret
$new = SignalBridge::regenerateWebhookSecret($webhookId);
```

**Available events:** `message.sent`, `message.delivered`, `message.failed`, `message.permanently_failed`, `payment.completed`, `payment.failed`, `*` (all)

---

## Exception Handling

```php
use Nugsoft\SignalBridge\Exceptions\InsufficientBalanceException;
use Nugsoft\SignalBridge\Exceptions\InsufficientPermissionsException;
use Nugsoft\SignalBridge\Exceptions\NoClientException;
use Nugsoft\SignalBridge\Exceptions\RateLimitedException;
use Nugsoft\SignalBridge\Exceptions\ServiceUnavailableException;
use Nugsoft\SignalBridge\Exceptions\SignalBridgeException;
use Nugsoft\SignalBridge\Exceptions\UnauthorizedException;
use Nugsoft\SignalBridge\Exceptions\ValidationException;

try {
    SignalBridge::sms()->send('256700000000', 'Hello');

} catch (InsufficientBalanceException $e) {
    $required  = $e->getRequiredBalance();
    $available = $e->getCurrentBalance();

} catch (ValidationException $e) {
    $errors     = $e->getErrors();
    $firstError = $e->getFirstError();

} catch (RateLimitedException $e) {
    // Slow down requests

} catch (ServiceUnavailableException $e) {
    // No active vendor configured

} catch (UnauthorizedException $e) {
    // Invalid or expired token

} catch (InsufficientPermissionsException $e) {
    // Role doesn't have access

} catch (SignalBridgeException $e) {
    $data = $e->getData(); // Raw API response body
}
```

---

## Real-World Examples

### OTP / Verification Code

```php
use Nugsoft\SignalBridge\Facades\SignalBridge;
use Illuminate\Support\Facades\Cache;

public function sendOtp(Request $request): \Illuminate\Http\JsonResponse
{
    $code = random_int(100000, 999999);
    Cache::put("otp:{$request->phone}", $code, now()->addMinutes(5));

    SignalBridge::sms()->send(
        recipient: $request->phone,
        message: "Your verification code is {$code}. Valid for 5 minutes.",
        options: ['metadata' => ['action' => 'otp', 'ip' => $request->ip()]]
    );

    return response()->json(['success' => true]);
}
```

### Order Confirmation via WhatsApp Template

```php
public function confirmOrder(Order $order): void
{
    SignalBridge::whatsapp()->sendTemplate(
        recipient: $order->customer_phone,
        templateName: 'order_confirmation',
        components: [
            ['type' => 'body', 'parameters' => [
                ['type' => 'text', 'text' => $order->customer_name],
                ['type' => 'text', 'text' => $order->reference],
                ['type' => 'text', 'text' => number_format($order->total) . ' UGX'],
            ]],
        ]
    );
}
```

### Collect Payment and Listen via Webhook

```php
// 1. Initiate collection
$tx = SignalBridge::mobileMoney()->initiate(
    phone: $invoice->customer_phone,
    amount: $invoice->total,
    options: [
        'reference'    => $invoice->number,
        'callback_url' => route('webhooks.momo'),
        'metadata'     => ['invoice_id' => $invoice->id],
    ]
);

// 2. Handle webhook (routes/api.php → POST /webhooks/momo)
public function handle(Request $request): \Illuminate\Http\Response
{
    $status = $request->input('status');   // 'completed' | 'failed'
    $meta   = $request->input('metadata');

    if ($status === 'completed') {
        Invoice::find($meta['invoice_id'])->markPaid();
    }

    return response()->noContent();
}
```

### Batch SMS from Database

Fetch phone numbers from your database and send in batches. The API accepts up to 100 messages per request, so chunk large datasets accordingly.

```php
use App\Models\User;
use Nugsoft\SignalBridge\Facades\SignalBridge;

// Simple — send one message to all active users
User::where('is_active', true)
    ->select('phone', 'name')
    ->chunk(100, function ($users) {
        $messages = $users->map(fn ($user) => [
            'recipient' => $user->phone,
            'message'   => "Hi {$user->name}, your account has been updated.",
            'metadata'  => ['user_id' => $user->id],
        ])->toArray();

        SignalBridge::sms()->sendBatch($messages);
    });
```

```php
// Personalised messages — different content per recipient
$notifications = Notification::with('user')
    ->where('status', 'pending')
    ->get()
    ->chunk(100);

foreach ($notifications as $batch) {
    $messages = $batch->map(fn ($n) => [
        'recipient' => $n->user->phone,
        'message'   => $n->body,
        'metadata'  => ['notification_id' => $n->id],
    ])->toArray();

    $result = SignalBridge::sms()->sendBatch($messages);

    // Mark sent
    $batch->each->update(['status' => 'sent']);
}
```

```php
// With balance check before sending
$phones  = User::where('subscribed', true)->pluck('phone');
$balance = SignalBridge::getBalance('UGX');
$cost    = $phones->count() * SignalBridge::sms()->calculateSegments($message) * $balance['segment_price'];

if ($balance['available_balance'] < $cost) {
    throw new \RuntimeException("Insufficient balance. Need {$cost} UGX, have {$balance['available_balance']} UGX.");
}

$phones->chunk(100)->each(function ($chunk) use ($message) {
    SignalBridge::sms()->sendBatch(
        $chunk->map(fn ($phone) => ['recipient' => $phone, 'message' => $message])->toArray()
    );
});
```

---

## Configuration Reference

```php
// config/signalbridge.php
return [
    'url'               => env('SIGNALBRIDGE_URL', 'https://signal-bridge.nugsoftapps.net/api'),
    'token'             => env('SIGNALBRIDGE_TOKEN'),
    'timeout'           => env('SIGNALBRIDGE_TIMEOUT', 30),
    'default_sender_id' => env('SIGNALBRIDGE_SENDER_ID'),
    'logging'           => env('SIGNALBRIDGE_LOGGING', true),
];
```

| Variable | Required | Default | Description |
|---|---|---|---|
| `SIGNALBRIDGE_TOKEN` | ✅ | — | API authentication token |
| `SIGNALBRIDGE_URL` | ❌ | Production URL | API base URL |
| `SIGNALBRIDGE_TIMEOUT` | ❌ | `30` | HTTP request timeout (seconds) |
| `SIGNALBRIDGE_SENDER_ID` | ❌ | — | Default SMS sender ID (max 11 chars) |
| `SIGNALBRIDGE_LOGGING` | ❌ | `true` | Log API errors to Laravel log |

**Laravel compatibility:** 10, 11, 12, 13

---

## Testing

```bash
composer test
```

## License

MIT. See [LICENSE](LICENSE).

## Credits

- **Asaba William** — CTO

---

**Made with ❤️ by Nugsoft**
