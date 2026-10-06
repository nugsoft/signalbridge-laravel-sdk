## SignalBridge SDK

Sends SMS and WhatsApp, and collects mobile money, through the SignalBridge
gateway. Messages cost real money and reach real handsets — the rules below
exist because each one has gone wrong in practice.

### Setup

Requires `SIGNALBRIDGE_TOKEN` and `SIGNALBRIDGE_URL` in `.env`. `SIGNALBRIDGE_URL`
must include the `/api` suffix. Use the `SignalBridge` facade, or inject
`SignalBridgeClientInterface`.

```php
SignalBridge::sms()->send('256700000000', 'Your code is 1234');
```

Recipients are international format without a `+` (`256700000000`). Do not set a
sender ID: the gateway sends everything as `NUGSOFT` and ignores `sender_id`. SMS bodies are
capped at 1000 characters, WhatsApp at 4096. `scheduled_at` must be in the future.

### Never retry a send

The gateway charges a message the moment it accepts one, and there is no
idempotency key. Wrapping a send in a retry — `retry()`, a queued job with
`$tries > 1`, an HTTP middleware, a "resend" button without a guard — bills and
delivers it twice. A timeout is the dangerous case: it says nothing about whether
the gateway processed the request.

```php
retry(3, fn () => SignalBridge::sms()->send($to, $body));   // never do this
```

If a send times out, find out what happened before sending again:

```php
SignalBridge::sms()->messages(['recipient' => $to, 'start_date' => today()->toDateString()]);
```

### Never send real messages from tests or seeders

`Http::fake()` in every test that touches the SDK. Without it the call reaches
the gateway, sends an SMS, and bills the account — including from a test suite
run in CI.

```php
Http::fake(['*' => Http::response(['success' => true, 'data' => ['message_id' => 1]])]);
```

There is no test mode. `is_test => true` only labels a message — it is still
delivered and charged. For a manual check against the real gateway, use a number
you control.

### Never calculate cost yourself

Segment counting is not "length / 160". Unicode, emoji and the GSM escape table
all change it, and the gateway's rules are mirrored exactly by the SDK:

```php
$segments = SignalBridge::sms()->calculateSegments($message);
$cost     = SignalBridge::sms()->estimateCost($message, $segmentPrice);
```

Hand-rolling this produces quotes that do not match the invoice.

### Always handle insufficient balance

The account can run out mid-flow. Catch it specifically — a generic catch hides
a billing problem as a delivery problem.

```php
use Nugsoft\SignalBridge\Exceptions\InsufficientBalanceException;   // 402
use Nugsoft\SignalBridge\Exceptions\InsufficientPermissionsException; // 403, token ability
use Nugsoft\SignalBridge\Exceptions\RateLimitedException;           // 429
use Nugsoft\SignalBridge\Exceptions\UnauthorizedException;          // 401
use Nugsoft\SignalBridge\Exceptions\ValidationException;            // 422
```

`RateLimitedException` (429) is per-client and per-minute: back off, do not retry
in a tight loop.

### Token abilities

The gateway enforces what a token may do. A token created without a selection
gets `*` and reaches everything; a narrower one raises
`InsufficientPermissionsException` elsewhere.

| Ability | Needed by |
|---------|-----------|
| `sms:send` | `sms()->send()`, `sms()->sendBatch()` |
| `sms:read` | `sms()->status()`, `sms()->messages()` |
| `balance:read` | `getBalance()`, `getBalanceSummary()`, `getTransactions()` |
| `webhooks:read` / `webhooks:write` | reading / changing webhooks |
| `export:read` | `exportMessages()`, `exportTransactions()` |

WhatsApp and mobile money abilities cannot be granted yet, so those two channels
need a full-access (`*`) token.

### Delivery status

A send returns `queued`, not `delivered`. To learn the outcome, either register a
webhook, or poll:

```php
SignalBridge::sms()->status($messageId);                    // stored status
SignalBridge::sms()->messages(['ids' => $ids]);             // whole batch at once
```

`permanently_failed` means every retry was exhausted **and the charge was
refunded** — treat it as not-sent, not as a silent cost.

Do not call `status($id, refresh: true)` in a loop. It hits the vendor live and
is rate limited; the plain call is normally current within minutes.

### Verify every webhook

Webhooks are signed over the raw body. Use the helper — a hand-rolled check
usually hashes a re-encoded payload or compares with `===`, and both fail
silently:

```php
use Nugsoft\SignalBridge\Support\WebhookSignature;

abort_unless(WebhookSignature::verifyRequest($request, $secret), 403);
```

Valid events are `message.sent`, `message.delivered`, `message.failed`,
`message.permanently_failed` and `*`. Anything else is rejected with a 422. The
signing secret is returned once, when the webhook is created.

### Bulk sending

`sendBatch()` takes up to 100 messages per call and returns a `message_id` per
recipient — keep them to reconcile later with `messages(['ids' => ...])`. Do not
loop `send()` for bulk.

A batch stops at the first insufficient balance, so a partial batch is normal:
read `successful`, `failed` and the per-message results rather than assuming all
or nothing.

### Balances

`getBalance()` returns the resource under `data`, and reading one never creates
one — a currency with nothing stored reads as zero. Currency must be a
three-letter code.

```php
$balance = SignalBridge::getBalance('UGX')['data'];
$balance['available_balance'];   // balance + credit limit
$balance['segment_price'];       // pass to estimateCost()
```

Clients cannot credit themselves. Top-ups are an administrator action.

### WhatsApp and mobile money

```php
SignalBridge::whatsapp()->send('256700000000', 'Your order has shipped');
SignalBridge::mobileMoney()->initiate('256700000000', 15000, 'UGX', ['reference' => 'INV-1', 'note' => 'Invoice']);
```

Mobile money reads `reference` and `note` (the text shown to the payer) only.
Poll `mobileMoney()->verify($transactionId)` for the result.

Avoid `whatsapp()->sendTemplate()` for now: the gateway does not yet deliver
template sends as templates, so the message goes out empty and ends up
permanently failed. Use `whatsapp()->send()` within the 24-hour window.

### Not available

`mobileMoney()->disburse()` throws `ServiceUnavailableException`; the gateway
exposes no payout endpoint. `ussd()` is likewise unreleased. Collecting payments
with `mobileMoney()->initiate()` works normally.

### Do not log message bodies or recipient numbers

The SDK deliberately logs only status, message and error code. Keep it that way
in application code.
