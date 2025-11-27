<?php

namespace Nugsoft\SignalBridge\Exceptions;

use Exception;

class SignalBridgeException extends Exception
{
  protected array $data = [];

  public function __construct(string $message = '', int $code = 0, array $data = [], ?\Throwable $previous = null)
  {
    parent::__construct($message, $code, $previous);
    $this->data = $data;
  }

  public function getData(): array
  {
    return $this->data;
  }
}
