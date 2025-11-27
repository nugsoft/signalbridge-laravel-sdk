# SignalBridge Laravel SDK

[![Latest Version on Packagist](https://img.shields.io/packagist/v/nugsoft/signalbridge-laravel-sdk.svg?style=flat-square)](https://packagist.org/packages/nugsoft/signalbridge-laravel-sdk)
[![Total Downloads](https://img.shields.io/packagist/dt/nugsoft/signalbridge-laravel-sdk.svg?style=flat-square)](https://packagist.org/packages/nugsoft/signalbridge-laravel-sdk)

Laravel SDK for SignalBridge SMS Gateway - Send SMS messages through multiple vendors (SpeedaMobile, Africa's Talking) with a unified API. Built specifically for Nugsoft product/project teams.

## Features

- **Simple API** - Clean, Laravel-style interface
- **Balance Management** - Check balance, view transactions, get usage reports
- **Scheduled Messages** - Schedule SMS for future delivery
- **Segment Calculation** - Automatic cost estimation (GSM 7-bit vs Unicode)
- **Custom Exceptions** - Typed exceptions for better error handling
- **Facade Support** - Use `SignalBridge::sendSms()` syntax
- **Dependency Injection** - Constructor injection support
- **Laravel 10, 11, 12** - Compatible with modern Laravel versions
- **PHP 8.1+** - Modern PHP features
- **Queue** - Supports message queueing for reliable delivery with retry logic and monitoring via Laravel's queue system

## Requirements

- PHP 8.1 or higher
- Laravel 10.0, 11.0, or 12.0
- Guzzle HTTP 7.0+

## Installation

### 1. Install via Composer

```bash
composer require nugsoft/signalbridge-laravel-sdk
```

### 2. Publish Configuration (Optional)

```bash
php artisan vendor:publish --tag=signalbridge-config
```

This creates `config/signalbridge.php` where you can customize settings.

### 3. Configure Environment Variables

Add these to your `.env` file:

```env
SIGNALBRIDGE_TOKEN=your_api_token_here
```

**Getting Your API Token:**

Contact your system administrator or generate a token via:

```bash
curl -X POST https://signal-bridge.nugsoftstagging.com/api/tokens \
  -H "Content-Type: application/json" \
  -d '{
    "email": "your-product@nugsoft.com",
    "password": "your-password",
    "expires_in_days": 365
  }'
```

> [!NOTE]
> You can also generate your token through the SignalBridge settings page in your account.

## Usage

### Quick Start

#### Using the Facade (Recommended)

```php
use Nugsoft\SignalBridge\Facades\SignalBridge;

// Send SMS
$result = SignalBridge::sendSms(
    recipient: '256700000000',
    message: 'Hello from Laravel!',
    options: [
        'metadata' => ['user_id' => 123]
    ]
);
```

#### Using Dependency Injection

```php
use Nugsoft\SignalBridge\SignalBridgeClient;

class NotificationService
{
    public function __construct(
        private SignalBridgeClient $signalBridge
    ) {}

    public function sendWelcomeSms(string $phone, string $name)
    {
        return $this->signalBridge->sendSms(
            recipient: $phone,
            message: "Welcome {$name}! Thanks for joining us."
        );
    }
}
```

### Real-World Examples

#### 1. Send OTP/Verification Code

```php
use Nugsoft\SignalBridge\Facades\SignalBridge;
use Illuminate\Support\Facades\Cache;

public function sendOTP(Request $request)
{
    $code = rand(100000, 999999);

    // Store in cache for 5 minutes
    Cache::put("otp:{$request->phone}", $code, now()->addMinutes(5));

    try {
        $result = SignalBridge::sendSms(
            recipient: $request->phone,
            message: "Your verification code is {$code}. Valid for 5 minutes.",
            options: [
                'metadata' => [
                    'user_id' => $request->user()->id,
                    'action' => 'otp_verification',
                    'ip_address' => $request->ip(),
                ]
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully',
            'cost' => $result['data']['cost'],
        ]);

    } catch (\Nugsoft\SignalBridge\Exceptions\InsufficientBalanceException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Insufficient balance',
            'required' => $e->getRequiredBalance(),
            'available' => $e->getCurrentBalance(),
        ], 402);
    }
}
```

#### 2. Batch SMS Notifications

```php
use Nugsoft\SignalBridge\Facades\SignalBridge;

public function notifyStudents(Request $request)
{
    $students = $request->input('students'); // Array of student data

    // Build batch messages
    $messages = collect($students)->map(function ($student) {
        return [
            'recipient' => $student['phone'],
            'message' => "Hi {$student['name']}, your exam results are ready. Score: {$student['score']}/100",
            'metadata' => [
                'student_id' => $student['id'],
                'exam_id' => $request->exam_id,
            ]
        ];
    })->toArray();

    try {
        $result = SignalBridge::sendBatch($messages);

        return response()->json([
            'success' => true,
            'sent' => $result['data']['successful'],
            'failed' => $result['data']['failed'],
        ]);

    } catch (\Nugsoft\SignalBridge\Exceptions\ValidationException $e) {
        return response()->json([
            'success' => false,
            'errors' => $e->getErrors(),
        ], 422);
    }
}
```

#### 3. Scheduled Reminders

```php
use Nugsoft\SignalBridge\Facades\SignalBridge;
use Carbon\Carbon;

public function schedulePaymentReminders()
{
    $invoices = Invoice::whereDue(today()->addDay())->get();

    $messages = $invoices->map(function ($invoice) {
        return [
            'recipient' => $invoice->customer_phone,
            'message' => "Reminder: Invoice #{$invoice->number} of {$invoice->amount} UGX is due tomorrow.",
            'scheduled_at' => Carbon::tomorrow()->setTime(9, 0)->toIso8601String(),
            'metadata' => [
                'invoice_id' => $invoice->id,
                'type' => 'payment_reminder',
            ]
        ];
    })->toArray();

    return SignalBridge::sendBatch($messages);
}
```

#### 4. Balance Check Before Sending

```php
use Nugsoft\SignalBridge\Facades\SignalBridge;

public function sendBulkWithValidation(array $recipients, string $message)
{
    // Get current balance
    $balance = SignalBridge::getBalance('UGX');

    // Calculate cost
    $segments = SignalBridge::calculateSegments($message);
    $estimatedCost = count($recipients) * $segments * $balance['segment_price'];

    // Validate sufficient balance
    if ($balance['available_balance'] < $estimatedCost) {
        throw new \Exception(
            "Insufficient balance. Required: {$estimatedCost}, Available: {$balance['available_balance']}"
        );
    }

    // Proceed with sending
    $messages = collect($recipients)->map(fn($phone) => [
        'recipient' => $phone,
        'message' => $message,
    ])->toArray();

    return SignalBridge::sendBatch($messages);
}
```

#### 5. Usage Reporting

```php
use Nugsoft\SignalBridge\Facades\SignalBridge;

public function getMonthlyReport(string $month)
{
    $startDate = Carbon::parse($month)->startOfMonth()->format('Y-m-d');
    $endDate = Carbon::parse($month)->endOfMonth()->format('Y-m-d');

    $transactions = SignalBridge::getTransactions([
        'type' => 'debit',
        'start_date' => $startDate,
        'end_date' => $endDate,
        'per_page' => 100,
    ]);

    // Group by message type
    $summary = collect($transactions['data'])
        ->groupBy(fn($tx) => $tx['metadata']['type'] ?? 'general')
        ->map(fn($group) => [
            'count' => $group->count(),
            'cost' => $group->sum('amount'),
        ]);

    return [
        'period' => $month,
        'total_cost' => collect($transactions['data'])->sum('amount'),
        'by_type' => $summary,
    ];
}
```

## API Reference

### Send SMS

```php
SignalBridge::sendSms(
    recipient: '256700000000',
    message: 'Your message here',
    options: [
        'metadata' => [],                   // Optional: Custom data
        'is_test' => false,                 // Optional: Test mode flag
        'scheduled_at' => '2025-12-01...',  // Optional: ISO 8601 datetime
    ]
);
```

**Returns:**
```php
[
    'success' => true,
    'message' => 'SMS queued successfully',
    'data' => [
        'message_id' => 1234,
        'status' => 'queued',
        'vendor' => 'SpeedaMobile',
        'segments' => 1,
        'cost' => 75.00,
        'balance_after' => 9925.00
    ]
]
```

### Send Batch SMS

```php
SignalBridge::sendBatch(
    messages: [
        [
            'recipient' => '256700000000',
            'message' => 'Message 1',
            'metadata' => ['order_id' => 123]
        ],
        [
            'recipient' => '256700000001',
            'message' => 'Message 2',
            'metadata' => ['order_id' => 124]
        ],
    ],
    options: [
        'is_test' => false,
    ]
);
```

**Returns:**
```php
[
    'success' => true,
    'message' => 'Batch SMS processed: 2 successful, 0 failed',
    'data' => [
        'total' => 2,
        'successful' => 2,
        'failed' => 0,
        'messages' => [...]
    ]
]
```

### Get Balance

```php
$balance = SignalBridge::getBalance('UGX');
```

**Returns:**
```php
[
    'success' => true,
    'balance' => 100.00,
    'currency' => 'UGX',
    'available_balance' => 100.00,
    'credit_limit' => 0.00,
    'segment_price' => 0.02
]
```

### Get Balance Summary

```php
$summary = SignalBridge::getBalanceSummary();
```

### Get Transactions

```php
$transactions = SignalBridge::getTransactions([
    'per_page' => 15,
    'page' => 1,
    'type' => 'debit',
    'start_date' => '2025-11-01',
    'end_date' => '2025-11-30',
]);
```

### Calculate Segments

```php
$segments = SignalBridge::calculateSegments('Your message here');
// Returns: 1 (for messages up to 160 GSM chars or 70 Unicode chars)
```

### Estimate Cost

```php
$cost = SignalBridge::estimateCost(
    message: 'Your message here',
    segmentPrice: 0.02
);
// Returns: 0.02 (segments * price)
```

### Token Management

```php
// Get all tokens
$tokens = SignalBridge::getTokens();

// Revoke current token
$result = SignalBridge::revokeCurrentToken();
```

## Exception Handling

The SDK provides typed exceptions for better error handling:

```php
use Nugsoft\SignalBridge\Exceptions\InsufficientBalanceException;
use Nugsoft\SignalBridge\Exceptions\ValidationException;
use Nugsoft\SignalBridge\Exceptions\NoClientException;
use Nugsoft\SignalBridge\Exceptions\ServiceUnavailableException;
use Nugsoft\SignalBridge\Exceptions\SignalBridgeException;

try {
    SignalBridge::sendSms('256700000000', 'Test message');

} catch (InsufficientBalanceException $e) {
    // Handle insufficient balance
    $required = $e->getRequiredBalance();
    $current = $e->getCurrentBalance();
    $segments = $e->getSegments();

} catch (ValidationException $e) {
    // Handle validation errors
    $errors = $e->getErrors();
    $firstError = $e->getFirstError();

} catch (NoClientException $e) {
    // Handle no client associated

} catch (ServiceUnavailableException $e) {
    // Handle service unavailable (no SMS vendor configured)

} catch (SignalBridgeException $e) {
    // Handle other API errors
    $data = $e->getData();
}
```

## SMS Segments & Pricing

Messages are charged based on segments:

**GSM 7-bit Encoding** (standard characters):
- Single segment: Up to 160 characters
- Multi-part: 153 characters per segment

**Unicode Encoding** (emojis, Arabic, Chinese, etc.):
- Single segment: Up to 70 characters
- Multi-part: 67 characters per segment

The SDK automatically detects encoding and calculates segments.

## Configuration

After publishing the config file, you can customize:

```php
// config/signalbridge.php

return [
    'url' => env('SIGNALBRIDGE_URL', 'https://signal-bridge.nugsoftstagging.com/api'),
    'token' => env('SIGNALBRIDGE_TOKEN'),
    'timeout' => env('SIGNALBRIDGE_TIMEOUT', 30),
    'default_sender_id' => env('SIGNALBRIDGE_SENDER_ID', config('app.name')),
    'logging' => env('SIGNALBRIDGE_LOGGING', true),
];
```

## Testing

```bash
composer test
```

## License

The MIT License (MIT). This package is proprietary to Nugsoft.

## Credits

- **Asaba William**
- **Nugsoft Dev team**

---

**Made with ❤️ by Nugsoft**
