<?php

namespace Nugsoft\SignalBridge\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array sendSms(string $recipient, string $message, array $options = [])
 * @method static array sendBatch(array $messages, array $options = [])
 * @method static array getBalance(string $currency = 'UGX')
 * @method static array getBalanceSummary()
 * @method static array getTransactions(array $filters = [])
 * @method static array getTokens()
 * @method static array revokeCurrentToken()
 * @method static int calculateSegments(string $message)
 * @method static float estimateCost(string $message, float $segmentPrice)
 *
 * @see \Nugsoft\SignalBridge\SignalBridgeClient
 */
class SignalBridge extends Facade
{
  /**
   * Get the registered name of the component.
   */
  protected static function getFacadeAccessor(): string
  {
    return 'signalbridge';
  }
}
