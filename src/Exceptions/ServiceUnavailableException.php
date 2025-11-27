<?php

namespace Nugsoft\SignalBridge\Exceptions;

class ServiceUnavailableException extends SignalBridgeException
{
  public function __construct(string $message = 'SMS service is currently unavailable')
  {
    parent::__construct($message, 503);
  }
}
