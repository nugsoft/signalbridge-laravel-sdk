<?php

return [
  /*
    |--------------------------------------------------------------------------
    | SignalBridge API URL
    |--------------------------------------------------------------------------
    |
    | The base URL for the SignalBridge API. For production, use:
    | https://signal-bridge.nugsoftstagging.com/api
    |
    | For local development, use:
    | http://localhost:8000/api
    |
    */

  'url' => env('SIGNALBRIDGE_URL', 'https://signal-bridge.nugsoftstagging.com/api'),

  /*
    |--------------------------------------------------------------------------
    | SignalBridge API Token
    |--------------------------------------------------------------------------
    |
    | Your SignalBridge API authentication token. You can generate this
    | from the SignalBridge dashboard or via the API token creation endpoint.
    |
    | IMPORTANT: Keep this token secure and never commit it to version control.
    |
    */

  'token' => env('SIGNALBRIDGE_TOKEN'),

  /*
    |--------------------------------------------------------------------------
    | Default Request Timeout
    |--------------------------------------------------------------------------
    |
    | The default timeout (in seconds) for API requests. Batch operations
    | automatically use a longer timeout (60 seconds).
    |
    */

  'timeout' => env('SIGNALBRIDGE_TIMEOUT', 30),

  /*
    |--------------------------------------------------------------------------
    | Default Sender ID
    |--------------------------------------------------------------------------
    |
    | The default sender ID to use when sending SMS messages. This can be
    | overridden per message by passing a 'sender_id' in the options array.
    |
    | Maximum 11 characters. Must be registered with your SMS vendor.
    |
    */

  'default_sender_id' => env('SIGNALBRIDGE_SENDER_ID', config('app.name')),

  /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Enable or disable logging of API errors. When enabled, all API errors
    | will be logged to your application's log file.
    |
    */

  'logging' => env('SIGNALBRIDGE_LOGGING', true),
];
