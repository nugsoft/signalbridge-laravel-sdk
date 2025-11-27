# Changelog

All notable changes to `signalbridge-laravel-sdk` will be documented in this file.

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
