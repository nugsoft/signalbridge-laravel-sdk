## SignalBridge SDK

Sends SMS and WhatsApp, and collects mobile money, through the SignalBridge
gateway. Messages cost real money and reach real handsets — the rules below
exist because each one has gone wrong in practice.

### Setup

Requires `SIGNALBRIDGE_TOKEN` and `SIGNALBRIDGE_URL` in `.env`. Use the
`SignalBridge` facade, or inject `SignalBridgeClientInterface`.

```php
SignalBridge::sms()->send('256700000000', 'Your code is 1234');
```

Recipients are international format without a `+` (`256700000000`). Sender IDs
are at most 11 characters and must be registered with the vendor.

### Never send real messages from tests or seeders

`Http::fake()` in every test that touches the SDK. Without it the call reaches
the gateway, sends an SMS, and bills the account — including from a test suite
run in CI.

```php
Http::fake(['*' => Http::response(['success' => true, 'data' => ['message_id' => 1]])]);
```

For a manual check against the real gateway, pass `is_test => true` and use a
number you control.

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
use Nugsoft\SignalBridge\Exceptions\InsufficientBalanceException;
use Nugsoft\SignalBridge\Exceptions\RateLimitedException;
use Nugsoft\SignalBridge\Exceptions\ValidationException;
```

`RateLimitedException` (429) is per-client and per-minute: back off, do not retry
in a tight loop.

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
`message.permanently_failed` and `*`. Anything else is rejected with a 422.

### Bulk sending

`sendBatch()` takes up to 100 messages per call and returns a `message_id` per
recipient — keep them to reconcile later with `messages(['ids' => ...])`. Do not
loop `send()` for bulk.

### Not available

`mobileMoney()->disburse()` throws `ServiceUnavailableException`; the gateway
exposes no payout endpoint. `ussd()` is likewise unreleased. Collecting payments
with `mobileMoney()->initiate()` works normally.

### Do not log message bodies or recipient numbers

The SDK deliberately logs only status, message and error code. Keep it that way
in application code.
