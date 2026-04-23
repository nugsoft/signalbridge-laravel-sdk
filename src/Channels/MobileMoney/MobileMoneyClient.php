<?php

namespace Nugsoft\SignalBridge\Channels\MobileMoney;

use Nugsoft\SignalBridge\Channels\BaseChannelClient;
use Nugsoft\SignalBridge\Exceptions\ValidationException;

class MobileMoneyClient extends BaseChannelClient
{
  /**
   * Initiate a mobile money collection (request-to-pay).
   *
   * The response returns a transaction ID and status 'pending'.
   * The final status is delivered via webhook or polled via verify().
   *
   * @param  string  $phone     Payer's phone number in E.164 format (e.g. '256700000000')
   * @param  float   $amount    Amount to collect (must be > 0)
   * @param  string  $currency  Currency code (default: 'UGX')
   * @param  array   $options   Optional: reference, description, metadata, callback_url
   *
   * @example
   * $tx = SignalBridge::mobileMoney()->initiate(
   *     phone: '256700000000',
   *     amount: 5000,
   *     currency: 'UGX',
   *     options: [
   *         'reference'    => 'INV-001',
   *         'description'  => 'Invoice payment',
   *         'callback_url' => 'https://yourapp.com/webhooks/momo',
   *     ]
   * );
   * $transactionId = $tx['data']['transaction_id'];
   */
  public function initiate(string $phone, float $amount, string $currency = 'UGX', array $options = []): array
  {
    if (empty(trim($phone))) {
      throw new ValidationException('Phone number is required');
    }

    if ($amount <= 0) {
      throw new ValidationException('Amount must be greater than zero');
    }

    $payload = [
      'phone'        => $phone,
      'amount'       => $amount,
      'currency'     => $currency,
      'reference'    => $options['reference'] ?? null,
      'description'  => $options['description'] ?? null,
      'metadata'     => $options['metadata'] ?? [],
      'callback_url' => $options['callback_url'] ?? null,
    ];

    $response = $this->http()->post("{$this->baseUrl}/mobile-money/initiate", $payload);

    if ($response->failed()) {
      $this->handleError($response);
    }

    return $this->parseResponse($response);
  }

  /**
   * Verify (poll) the status of a mobile money transaction.
   *
   * Use this to check whether a pending collection has completed.
   * Prefer webhooks over polling where possible.
   *
   * @param  string  $transactionId  UUID returned from initiate()
   */
  public function verify(string $transactionId): array
  {
    if (empty(trim($transactionId))) {
      throw new ValidationException('Transaction ID is required');
    }

    $response = $this->http()->get("{$this->baseUrl}/mobile-money/transactions/{$transactionId}");

    if ($response->failed()) {
      $this->handleError($response);
    }

    return $this->parseResponse($response);
  }

  /**
   * Disburse (send) money to a mobile money wallet.
   *
   * @param  string  $phone     Recipient's phone number in E.164 format
   * @param  float   $amount    Amount to disburse (must be > 0)
   * @param  string  $currency  Currency code (default: 'UGX')
   * @param  array   $options   Optional: reference, description, metadata
   *
   * @example
   * SignalBridge::mobileMoney()->disburse(
   *     phone: '256700000000',
   *     amount: 50000,
   *     options: ['reference' => 'PAYOUT-42', 'description' => 'Staff commission']
   * );
   */
  public function disburse(string $phone, float $amount, string $currency = 'UGX', array $options = []): array
  {
    if (empty(trim($phone))) {
      throw new ValidationException('Phone number is required');
    }

    if ($amount <= 0) {
      throw new ValidationException('Amount must be greater than zero');
    }

    $payload = [
      'phone'       => $phone,
      'amount'      => $amount,
      'currency'    => $currency,
      'reference'   => $options['reference'] ?? null,
      'description' => $options['description'] ?? null,
      'metadata'    => $options['metadata'] ?? [],
    ];

    $response = $this->http()->post("{$this->baseUrl}/mobile-money/disburse", $payload);

    if ($response->failed()) {
      $this->handleError($response);
    }

    return $this->parseResponse($response);
  }
}
