<?php

namespace Nugsoft\SignalBridge\Channels\MobileMoney;

use Illuminate\Http\Client\Response;
use Nugsoft\SignalBridge\Channels\BaseChannelClient;
use Nugsoft\SignalBridge\Exceptions\ServiceUnavailableException;
use Nugsoft\SignalBridge\Exceptions\ValidationException;

class MobileMoneyClient extends BaseChannelClient
{
  /**
   * Ask a payer to pay (request-to-pay).
   *
   * The payer gets a prompt on their phone, so the response is not the
   * outcome: its status is 'pending'. It carries SignalBridge's reference for
   * the transaction in data.reference — store it and pass it to verify().
   *
   * @param  string  $phone     Payer's phone number, digits only (e.g. '256700000000' or '0700000000')
   * @param  float   $amount    Amount to collect (must be > 0)
   * @param  string  $currency  Currency code (default: 'UGX')
   * @param  array   $options   Optional: note — shown to the payer, at most 100 characters
   *                            ('description' is accepted as an alias)
   *
   * @example
   * $tx = SignalBridge::mobileMoney()->initiate(
   *     phone: '256700000000',
   *     amount: 5000,
   *     currency: 'UGX',
   *     options: ['note' => 'Invoice INV-001'],
   * );
   * $reference = $tx['data']['reference'];
   *
   * @throws ServiceUnavailableException while the gateway has mobile money collections switched off
   * @throws ValidationException         when the request is invalid, or the provider refused it; for a
   *                                     refusal, getData() holds the transaction, reference included
   */
  public function initiate(string $phone, float $amount, string $currency = 'UGX', array $options = []): array
  {
    if (empty(trim($phone))) {
      throw new ValidationException('Phone number is required');
    }

    if ($amount <= 0) {
      throw new ValidationException('Amount must be greater than zero');
    }

    // The gateway gives every transaction its own reference and ignores one
    // sent with the request, so accepting it here would let callers poll by an
    // ID that does not exist.
    if (array_key_exists('reference', $options)) {
      throw new ValidationException(
        'SignalBridge assigns the reference: read it from data.reference in the response instead of passing one.'
      );
    }

    $payload = [
      'phone'    => $phone,
      'amount'   => $amount,
      'currency' => strtoupper($currency),
    ];

    // 'note' is the text shown to the payer, and the only descriptive field the
    // gateway reads.
    $note = $options['note'] ?? $options['description'] ?? null;

    if (! empty($note)) {
      if (mb_strlen($note) > 100) {
        throw new ValidationException('The note must be at most 100 characters');
      }

      $payload['note'] = $note;
    }

    $response = $this->http()->post("{$this->baseUrl}/mobile-money/initiate", $payload);

    if ($response->failed()) {
      $this->handleMobileMoneyError($response);
    }

    return $this->parseResponse($response);
  }

  /**
   * Ask what became of a transaction.
   *
   * Statuses only move forward: 'pending', then possibly 'processing', then
   * 'completed' or 'failed' ('cancelled', 'rejected' and 'rolled_back' exist
   * for payouts). A 404 means no transaction of yours has that reference.
   *
   * @param  string  $reference  data.reference from initiate()
   */
  public function verify(string $reference): array
  {
    if (empty(trim($reference))) {
      throw new ValidationException('Reference is required');
    }

    $response = $this->http()->get("{$this->baseUrl}/mobile-money/transactions/".rawurlencode($reference));

    if ($response->failed()) {
      $this->handleMobileMoneyError($response);
    }

    return $this->parseResponse($response);
  }

  /**
   * The two answers the shared handler would misread.
   *
   * The gateway answers 403 while collections are switched off; the shared
   * handler would call that a token without a client. And a payment the
   * provider refused is a 422 that still carries the transaction, whose
   * reason belongs in the message.
   */
  protected function handleMobileMoneyError(Response $response): never
  {
    $data = $response->json();
    $data = is_array($data) ? $data : [];
    $message = (string) ($data['message'] ?? '');

    if ($response->status() === 403 && str_contains(strtolower($message), 'not available')) {
      throw new ServiceUnavailableException($message);
    }

    $transaction = $data['data'] ?? null;

    if ($response->status() === 422 && is_array($transaction) && ($transaction['status'] ?? null) === 'failed') {
      $reason = $transaction['status_message'] ?? null;

      throw new ValidationException(
        rtrim($message ?: 'The payment request was refused.', '.').($reason ? ": {$reason}" : '.'),
        [],
        $transaction
      );
    }

    $this->handleError($response);
  }

  /**
   * Disburse (send) money to a mobile money wallet.
   *
   * @param  string  $phone     Recipient's phone number in E.164 format
   * @param  float   $amount    Amount to disburse (must be > 0)
   * @param  string  $currency  Currency code (default: 'UGX')
   * @param  array   $options   Optional: note
   *
   * @example
   * SignalBridge::mobileMoney()->disburse(
   *     phone: '256700000000',
   *     amount: 50000,
   *     options: ['note' => 'Staff commission']
   * );
   *
   * @note NOT YET AVAILABLE. The gateway exposes no disbursement endpoint, so
   *       this always throws ServiceUnavailableException rather than pretending
   *       to move money.
   *
   * @throws ServiceUnavailableException always, until the endpoint ships
   */
  public function disburse(string $phone, float $amount, string $currency = 'UGX', array $options = []): array
  {
    // The gateway has no /mobile-money/disburse route. The driver behind it is
    // written, but sending money out is not exposed over the API yet. Calling
    // through would 404, which the error handler reports as a bad
    // SIGNALBRIDGE_URL and sends you looking in the wrong place.
    throw new ServiceUnavailableException(
      'Mobile money disbursement is not available yet: the SignalBridge API does not expose '
        .'/mobile-money/disburse. Use initiate() to collect payments. Contact the SignalBridge '
        .'team if you need payouts enabled.'
    );
  }
}
