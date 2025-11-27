<?php

namespace Nugsoft\SignalBridge\Exceptions;

class NoClientException extends SignalBridgeException
{
  public function __construct(string $message = 'No client associated with your account')
  {
    parent::__construct($message, 403);
  }
}
