<?php

namespace Nugsoft\SignalBridge\Exceptions;

class InsufficientBalanceException extends SignalBridgeException
{
  public function __construct(string $message = 'Insufficient balance', array $data = [])
  {
    parent::__construct($message, 402, $data);
  }

  public function getRequiredBalance(): ?float
  {
    return $this->data['required_balance'] ?? null;
  }

  public function getCurrentBalance(): ?float
  {
    return $this->data['current_balance'] ?? null;
  }

  public function getSegments(): ?int
  {
    return $this->data['segments'] ?? null;
  }
}
