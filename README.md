# SignalBridge Laravel SDK

[![Latest Version on Packagist](https://img.shields.io/packagist/v/nugsoft/signalbridge-laravel-sdk.svg?style=flat-square)](https://packagist.org/packages/nugsoft/signalbridge-laravel-sdk)
[![Total Downloads](https://img.shields.io/packagist/dt/nugsoft/signalbridge-laravel-sdk.svg?style=flat-square)](https://packagist.org/packages/nugsoft/signalbridge-laravel-sdk)

Official Laravel SDK for [SignalBridge](https://signal-bridge.nugsoftapps.net) — a unified multi-channel communication and payment gateway.

Send SMS, WhatsApp messages, and initiate Mobile Money transactions through a single, clean Laravel API. Built by Nugsoft.

## Channels

| Channel | Status | Methods |
|---|---|---|
| **SMS** | ✅ Available | `send()`, `sendBatch()`, `status()`, `messages()`, `calculateSegments()`, `estimateCost()` |
| **WhatsApp** | ✅ Available | `sendTemplate()`, `send()`, `sendFlow()`, templates, Flows, received messages |
| **Mobile Money** | ⚠️ Unreleased on the gateway | `initiate()`, `verify()` |
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

## Using this SDK with an AI coding agent

The package ships agent guidance at `resources/boost/guidelines/core.md`,
covering the things that are easy to get expensively wrong — sending real
messages from a test suite, hand-rolling segment costs, skipping webhook
signature verification.

If your project uses [Laravel Boost](https://github.com/laravel/boost), Boost
finds it automatically — it scans installed packages for
`resources/boost/guidelines/` — and `php artisan boost:install` offers
`nugsoft/signalbridge-laravel-sdk` among the third-party guidelines, merging the
ones you pick into your `CLAUDE.md`, `.github/copilot-instructions.md` and
`.junie/guidelines.md`.

If the guidance does not appear, check your `boost.json`: the `guidelines` key
records your selection and acts as an allow-list once it exists, so a
`"guidelines": []` left by an earlier install excludes every third-party package.

Without Boost, one line in your `CLAUDE.md` (or `AGENTS.md`) pulls it in:

```md
@vendor/nugsoft/signalbridge-laravel-sdk/resources/boost/guidelines/core.md
```

For other agents, copy the file's contents into whatever instructions file they
read. See [AGENTS.md](AGENTS.md) for the details.

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
  -d '{"email": "you@example.com", "password": "your-password", "token_name": "My App", "expires_in_days": 365}'
```

The token comes back once, as `data.token`.

> You can also generate tokens from the SignalBridge dashboard under **Settings → API Tokens**.

### Token abilities

A token carries abilities and the gateway checks them on every request. Created
without an `abilities` list it gets `*` and reaches everything, which is what
existing tokens carry — so nothing needs changing unless you want to narrow one.

| Ability | Allows |
|---------|--------|
| `sms:send` | `sms()->send()`, `sms()->sendBatch()` |
| `sms:read` | `sms()->status()`, `sms()->messages()` |
| `balance:read` | `getBalance()`, `getBalanceSummary()`, `getTransactions()` |
| `balance:request-credit` | asking administrators for a top-up |
| `webhooks:read` / `webhooks:write` | reading / changing webhooks |
| `export:read` | `exportMessages()`, `exportTransactions()` |

A call made with a token that lacks the ability throws
`InsufficientPermissionsException`; the response names the missing ability.
Listing and revoking your own tokens is always allowed.

> `mobile-money:send` and `mobile-money:read` are not issuable at the moment: the mobile money channel is unreleased, so reaching it needs a full-access (`*`) token. The WhatsApp abilities are `whatsapp:send`, `whatsapp:templates`, `whatsapp:flows` and `whatsapp:read`.


---

## Usage

### Quick Start

```php
use Nugsoft\SignalBridge\Facades\SignalBridge;

// SMS
SignalBridge::sms()->send('256700000000', 'Hello from SignalBridge!');

// WhatsApp
SignalBridge::whatsapp()->sendTemplate('256700000000', 'fee_reminder', ['John', 'UGX 50,000']);

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
        'metadata'     => ['user_id' => 42], // Optional: stored for your records
        'is_test'      => false,             // Optional: a label only — still sent and charged
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

WhatsApp only lets a business **start** a conversation with a **template** it has
approved. So: submit a template once, wait for WhatsApp's approval, then send it as
often as you like. Free text and Flows are delivered only within **24 hours of the
person's last message to you**. You never need Meta credentials — SignalBridge holds
them, and handles all of WhatsApp's encryption.

Your token needs `whatsapp:send`, plus `whatsapp:templates`, `whatsapp:flows` and
`whatsapp:read` for the matching methods (or a full-access `*` token).

### Submit a template

```php
SignalBridge::whatsapp()->createTemplate([
    'name' => 'fee_reminder',
    'category' => 'utility',            // utility | marketing | authentication
    'body' => 'Hello {{1}}, your fee balance is {{2}}. Please pay by Friday.',
    'examples' => ['John', 'UGX 50,000'],
    'buttons' => [['type' => 'url', 'text' => 'Pay now', 'url' => 'https://pay.example.com']],
]);
```

Review usually takes minutes. You get a `template.approved` (or `template.rejected`)
webhook, or check with `listTemplates('approved')` / `getTemplate($id, refresh: true)`.

### Send a template

```php
SignalBridge::whatsapp()->sendTemplate('256700000000', 'fee_reminder', ['John', 'UGX 50,000']);

// A template that starts with a document or image takes the file as a link
SignalBridge::whatsapp()->sendTemplate('256700000000', 'weekly_report', ['Kampala branch'], [
    'header' => ['type' => 'document', 'url' => 'https://files.example.com/report.pdf', 'filename' => 'report.pdf'],
]);

// A one-time code: an authentication template takes the code as its one variable
SignalBridge::whatsapp()->sendTemplate('256700000000', 'login_code', ['482913']);
```

> `sendTemplate()` takes the variables as a plain list. The Meta `components`
> structure older versions asked for is built by SignalBridge.

### Free text (within 24 hours of their last message)

```php
SignalBridge::whatsapp()->send('256700000000', 'Thanks — we have received your payment.');
```

### Flows

Flows are forms customers fill in inside WhatsApp — bookings, registrations,
surveys. Design one in WhatsApp's Flow Builder, export its JSON, and:

```php
$flow = SignalBridge::whatsapp()->createFlow([
    'name' => 'spa_booking',
    'categories' => ['appointment_booking'],
    'flow_json' => $flowJson,                                  // array or string
    'endpoint_url' => 'https://your-app.example.com/whatsapp/flow', // only if it fetches live data
]);
$secret = $flow['endpoint_secret'];   // shown once — keep it to verify calls

SignalBridge::whatsapp()->publishFlow($flow['data']['id']);

// Within 24 hours of their last message, as an interactive message:
SignalBridge::whatsapp()->sendFlow('256700000000', 'spa_booking', 'Book your next session', 'Book now');

// Or to start a conversation, through a template's Flow button:
SignalBridge::whatsapp()->sendTemplate('256700000000', 'booking_invite', [], ['flow' => ['data' => ['offer' => 'weekend']]]);
```

The customer's answers arrive as a `flow.completed` webhook.

**If your Flow fetches live data**, WhatsApp calls a data endpoint while the customer
fills it in. Those calls are encrypted; SignalBridge decrypts them and posts them to
your `endpoint_url` as plain JSON. Reply with the next screen as plain JSON within a
few seconds — SignalBridge encrypts it for WhatsApp:

```php
Route::post('/whatsapp/flow', function (Request $request) {
    abort_unless(WebhookSignature::verifyRequest($request, config('services.signalbridge.flow_secret')), 401);

    // $request: event, flow, action (INIT | data_exchange | BACK), screen, data, flow_token, message_id, recipient
    return [
        'screen' => 'SLOTS',
        'data' => ['slots' => ['10:00', '11:00', '14:00']],
    ];
});
```

### Messages customers send you

Replies to your messages, completed Flows and new messages from customers you last
contacted arrive as `message.received` and `flow.completed` webhooks. You can also ask:

```php
SignalBridge::whatsapp()->received(['since' => now()->subHour()->toIso8601String()]);

// The photo, document or voice note a customer sent:
$bytes = SignalBridge::whatsapp()->downloadMedia($receivedMessageId);
```

### WhatsApp webhook events

| Event | When |
|---|---|
| `message.sent` / `message.delivered` / `message.read` / `message.failed` | Your message's progress (`failed` is refunded) |
| `message.received` | A customer messaged you |
| `flow.completed` | A customer submitted your Flow — the answers are in `content.answers` |
| `template.approved` / `template.rejected` / `template.paused` / `template.disabled` | WhatsApp's verdict on your template |

Template and Flow events are not in a webhook's default subscription — add them, or subscribe to `*`.

---

## Mobile Money

### Collect a Payment (Request-to-Pay)

```php
$tx = SignalBridge::mobileMoney()->initiate(
    phone: '256700000000',
    amount: 15000,
    currency: 'UGX',
    options: [
        'reference' => 'INV-2026-001',
        // Shown to the payer on their handset. 'description' is accepted as an
        // alias for it.
        'note'      => 'Invoice payment',
    ]
);

$transactionId = $tx['data']['transaction_id'];
$status        = $tx['data']['status']; // 'pending'
```

> The gateway reads `reference` and `note` only. `metadata` and `callback_url`
> were accepted here previously and silently dropped by the API, so they are no
> longer sent — listen for the result with `verify()` until mobile money
> callbacks are available.

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
        variables: [$order->customer_name, $order->reference, number_format($order->total).' UGX'],
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
    'logging'           => env('SIGNALBRIDGE_LOGGING', true),
];
```

| Variable | Required | Default | Description |
|---|---|---|---|
| `SIGNALBRIDGE_TOKEN` | ✅ | — | API authentication token |
| `SIGNALBRIDGE_URL` | ❌ | Production URL | API base URL |
| `SIGNALBRIDGE_TIMEOUT` | ❌ | `30` | HTTP request timeout (seconds) |
| `SIGNALBRIDGE_SENDER_ID` | ❌ | — | **Deprecated, has no effect.** The gateway sends every message as `NUGSOFT` |
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
