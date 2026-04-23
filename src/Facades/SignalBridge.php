<?php

namespace Nugsoft\SignalBridge\Facades;

use Illuminate\Support\Facades\Facade;
use Nugsoft\SignalBridge\Channels\MobileMoney\MobileMoneyClient;
use Nugsoft\SignalBridge\Channels\Sms\SmsClient;
use Nugsoft\SignalBridge\Channels\Ussd\UssdClient;
use Nugsoft\SignalBridge\Channels\WhatsApp\WhatsAppClient;
use Nugsoft\SignalBridge\Exceptions\InsufficientBalanceException;
use Nugsoft\SignalBridge\Exceptions\RateLimitedException;
use Nugsoft\SignalBridge\Exceptions\SignalBridgeException;
use Nugsoft\SignalBridge\Exceptions\UnauthorizedException;
use Nugsoft\SignalBridge\Exceptions\ValidationException;

/**
 * @method static SmsClient sms()
 * @method static WhatsAppClient whatsapp()
 * @method static MobileMoneyClient mobileMoney()
 * @method static UssdClient ussd()
 *
 * @method static array sendSms(string $recipient, string $message, array $options = [])
 * @method static array sendBatch(array $messages, array $options = [])
 * @method static array getBalance(string $currency = 'UGX')
 * @method static array getBalanceSummary()
 * @method static array getTransactions(array $filters = [])
 * @method static array getTokens()
 * @method static array revokeCurrentToken()
 * @method static array listWebhooks()
 * @method static array createWebhook(string $url, array $events = ['*'], bool $isActive = true)
 * @method static array getWebhook(int $webhookId)
 * @method static array updateWebhook(int $webhookId, array $data)
 * @method static array deleteWebhook(int $webhookId)
 * @method static array regenerateWebhookSecret(int $webhookId)
 * @method static string exportMessages(array $filters = [])
 * @method static string exportTransactions(array $filters = [])
 * @method static int calculateSegments(string $message)
 * @method static float estimateCost(string $message, float $segmentPrice)
 *
 * @throws ValidationException
 * @throws InsufficientBalanceException
 * @throws UnauthorizedException
 * @throws RateLimitedException
 * @throws SignalBridgeException
 *
 * @see \Nugsoft\SignalBridge\SignalBridgeClient
 */
class SignalBridge extends Facade
{
  protected static function getFacadeAccessor(): string
  {
    return 'signalbridge';
  }
}
