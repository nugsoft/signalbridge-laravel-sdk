# SignalBridge Laravel SDK

[![Latest Version on Packagist](https://img.shields.io/packagist/v/nugsoft/signalbridge-laravel-sdk.svg?style=flat-square)](https://packagist.org/packages/nugsoft/signalbridge-laravel-sdk)
[![Total Downloads](https://img.shields.io/packagist/dt/nugsoft/signalbridge-laravel-sdk.svg?style=flat-square)](https://packagist.org/packages/nugsoft/signalbridge-laravel-sdk)

Official Laravel SDK for [SignalBridge](https://signal-bridge.nugsoftapps.net) — a unified multi-channel communication and payment gateway.

Send SMS, WhatsApp messages, and initiate Mobile Money transactions through a single, clean Laravel API. Built by Nugsoft.

## Channels

| Channel | Status | Methods |
|---|---|---|
| **SMS** | ✅ Available | `send()`, `sendBatch()`, `status()`, `messages()`, `calculateSegments()`, `estimateCost()` |
| **WhatsApp** | ✅ Available | `send()`, `sendTemplate()` |
| **Mobile Money** | ✅ Available | `initiate()`, `verify()` |
| **Mobile Money payouts** | 🔜 Planned | `disburse()` — the gateway exposes no disbursement endpoint yet |
| **USSD** | 🔜 Planned | `push()`, `session()`, `respond()` |

## Features

- **Multi-channel API** — SMS, WhatsApp, Mobile Money, and USSD (planned) through one SDK
- **Channel-fluent interface** — `SignalBridge::sms()->send(...)`, `SignalBridge::whatsapp()->sendTemplate(...)`
- **Backward compatible** — existing `SignalBridge::sendSms()` calls still work
- **Delivery status** — poll what was delivered, per message or per batch
- **Balance & transaction management** — account-level operations on the main client
- **Webhook management** — full CRUD for outbound event webhooks, plus signature verification
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

// Delivery status, without hosting a webhook endpoint
SignalBridge::sms()->status($messageId);
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

### Delivery Status

Webhooks push a status change to you the moment it happens. These pull the
current status instead — no endpoint of your own to host, and the only option
that works for every message (see the note on bulk below).

```php
$sms = SignalBridge::sms();

// One message, by the id send() returned
$result = $sms->status(1234);
$result['data']['status'];        // 'queued' | 'sent' | 'delivered' | 'failed' | 'permanently_failed'
$result['data']['delivered_at'];  // ISO-8601, or null
$result['data']['error_message']; // why it failed, when it did

// Ask the vendor live rather than reading the stored status.
// Rate limited, and rarely needed — the gateway polls vendors in the background.
$sms->status(1234, refresh: true);
```

| Status | Meaning |
|---|---|
| `queued` | Accepted and charged, waiting for a worker |
| `processing` | Being handed to the vendor |
| `sent` | The vendor accepted it; delivery not yet confirmed |
| `delivered` | Confirmed delivered to the handset |
| `failed` | Rejected, or reported undelivered |
| `permanently_failed` | Every retry exhausted — **your balance was refunded** |

Only `delivered`, `failed` and `permanently_failed` are final.

#### Checking a whole batch

`sendBatch()` returns an id per accepted recipient. Ask about all of them at
once — `summary` counts the entire filtered set, not just the current page, so
one call tells you how the batch went:

```php
$result = SignalBridge::sms()->messages(['ids' => [1234, 1235, 1236]]);

$result['summary']['total'];                  // 3
$result['summary']['by_status']['delivered']; // 2
$result['summary']['by_status']['failed'];    // 1
```

Or ask what failed today, without tracking ids at all:

```php
SignalBridge::sms()->messages([
    'status'     => 'failed',
    'start_date' => now()->toDateString(),
]);
```

Filters: `ids`, `status`, `recipient`, `channel`, `start_date`, `end_date`,
`per_page`, `page`.

> **Bulk sends and SpeedaMobile.** SpeedaMobile does not send delivery reports
> for bulk traffic. SignalBridge polls it in the background instead, so
> `delivered` and `failed` are still accurate for those messages — they just
> arrive within minutes rather than seconds, and the usual `message.delivered`
> webhook still fires. Nothing to configure.

### Segment Calculation & Cost Estimation

```php
$sms = SignalBridge::sms();

$segments = $sms->calculateSegments('Hello World'); // 1
$cost     = $sms->estimateCost('Hello World', segmentPrice: 1.00); // 1.00
```

**Encoding rules:**
- GSM 7-bit (standard): 160 chars = 1 segment, 153 chars/segment thereafter
- Unicode (emoji, Arabic, Chinese…): 70 chars = 1 segment, 67 chars/segment thereafter

`calculateSegments()` mirrors the gateway's own billing calculation exactly, so
an estimate matches the invoice. Two things this means in practice:

- A newline does **not** make a message Unicode. Multi-line SMS bills as GSM.
- Neither do the escape-table characters `^ { } \ [ ] ~ | €`, so a templated
  message like `Hi {name}` is still GSM. (Strict GSM-7 charges two septets for
  each of those; SignalBridge bills them as one, and the SDK matches.)

Both implementations are covered by the same test cases. If you find a message
where the estimate and the charge disagree, that is a bug — please report it.

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

### Disburse (Send Money) — not available yet

```php
SignalBridge::mobileMoney()->disburse('256700000000', 50000);
// throws ServiceUnavailableException
```

The SignalBridge API does not expose a disbursement endpoint. The provider
driver exists server-side, but sending money out is not switched on, so this
method throws `ServiceUnavailableException` immediately rather than issuing a
request that would 404 and look like a misconfigured `SIGNALBRIDGE_URL`.

Collecting payments with `initiate()` works normally. Contact the SignalBridge
team if you need payouts enabled.

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

SignalBridge POSTs an event to your application whenever a message changes
state, so you do not have to poll for it.

```php
// Register a webhook
$webhook = SignalBridge::createWebhook(
    url: 'https://yourapp.com/webhooks/signalbridge',
    events: ['message.delivered', 'message.failed'],
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

**Available events:** `message.sent`, `message.delivered`, `message.failed`,
`message.permanently_failed`, and `*` for all of them. Anything else is
rejected with a 422.

`message.permanently_failed` fires when every retry has been exhausted. The
charge for that message is refunded to your balance at the same time, so you
will also see a `refund` entry in `getTransactions()`.

Webhooks can also be managed from the SignalBridge dashboard under
**Settings → Webhooks**, which shows delivery health and can send a test event
to check your endpoint is reachable.

### Verifying a Webhook

Every request is signed: an HMAC-SHA256 of the **raw request body**, keyed with
your webhook secret, in the `X-SignalBridge-Signature` header. Verify it before
acting on anything.

```php
use Nugsoft\SignalBridge\Support\WebhookSignature;

Route::post('/webhooks/signalbridge', function (Request $request) {
    if (! WebhookSignature::verifyRequest($request, config('services.signalbridge.webhook_secret'))) {
        abort(403);
    }

    $event = $request->header(WebhookSignature::EVENT_HEADER); // 'message.delivered'

    match ($event) {
        'message.delivered' => Order::markNotified($request->input('message_id')),
        'message.failed', 'message.permanently_failed' => Order::flagSmsFailure($request->input('message_id')),
        default => null,
    };

    return response()->noContent();
});
```

Two details this helper gets right, and both fail silently if you roll your own:

- It signs the **raw body**, not a re-encoded copy of the parsed payload.
  Re-encoding only matches while your JSON key order happens to match the
  sender's.
- It compares with `hash_equals`. A plain `===` leaks the expected digest one
  byte at a time to anyone willing to measure.

Exclude the route from CSRF protection, and return a 2xx quickly — a webhook
that fails ten times in a row is paused automatically.

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

// 2. Handle the provider callback (routes/api.php → POST /webhooks/momo)
//    Note: this is the mobile money provider calling your callback_url. It is
//    not a SignalBridge webhook, so it carries no X-SignalBridge-Signature —
//    verify it per your provider's own scheme.
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

### Reconciling a Batch

```php
// 1. Send, keeping the ids
$result = SignalBridge::sms()->sendBatch(
    collect($recipients)->map(fn ($r) => [
        'recipient' => $r->phone,
        'message'   => "Hi {$r->name}, your statement is ready.",
    ])->all()
);

$ids = collect($result['data']['messages'])
    ->where('success', true)
    ->pluck('data.message_id')
    ->all();

// 2. Later — a scheduled job, say — ask how they did
$status = SignalBridge::sms()->messages(['ids' => $ids]);

logger()->info('Statement run', $status['summary']['by_status']);
// ['delivered' => 480, 'failed' => 12, 'sent' => 8]

// 3. Chase only the ones that failed
foreach ($status['data'] as $message) {
    if (in_array($message['status'], ['failed', 'permanently_failed'], true)) {
        Recipient::wherePhone($message['recipient'])->first()?->flagUndeliverable(
            $message['error_message']
        );
    }
}
```

> Works for bulk sends through SpeedaMobile too, which never reports delivery
> by webhook — SignalBridge polls it for you.

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
