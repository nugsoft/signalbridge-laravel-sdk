<?php

return [
  /*
    |--------------------------------------------------------------------------
    | SignalBridge API URL
    |--------------------------------------------------------------------------
    |
    | The base URL for the SignalBridge API.
    |
    | WARNING: The default value below is the staging/development server.
    | Always set SIGNALBRIDGE_URL explicitly in production.
    |
    */

  'url' => env('SIGNALBRIDGE_URL', 'https://signal-bridge.nugsoftapps.net/api'),

  /*
    |--------------------------------------------------------------------------
    | SignalBridge API Token
    |--------------------------------------------------------------------------
    |
    | Your SignalBridge API authentication token. Generate this from the
    | SignalBridge dashboard or via the API token creation endpoint.
    |
    | IMPORTANT: Never commit this value to version control.
    |
    */

  'token' => env('SIGNALBRIDGE_TOKEN'),

  /*
    |--------------------------------------------------------------------------
    | Default Request Timeout
    |--------------------------------------------------------------------------
    |
    | Timeout in seconds for all API requests, including batch operations.
    | Increase this value if you are sending very large batches.
    |
    */

  'timeout' => env('SIGNALBRIDGE_TIMEOUT', 30),

  /*
    |--------------------------------------------------------------------------
    | Default Sender ID
    |--------------------------------------------------------------------------
    |
    | Sender ID shown to recipients. Maximum 11 characters.
    | Must be registered with your SMS vendor.
    | Can be overridden per message via the 'sender_id' option.
    |
    */

  'default_sender_id' => env('SIGNALBRIDGE_SENDER_ID'),

  /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | When enabled, API errors are logged to your application log.
    | Only the HTTP status, error message, and error code are logged —
    | message content and recipient numbers are never written to logs.
    |
    */

  'logging' => env('SIGNALBRIDGE_LOGGING', true),
];
