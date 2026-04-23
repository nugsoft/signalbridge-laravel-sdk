<?php

namespace Nugsoft\SignalBridge\Exceptions;

class InsufficientPermissionsException extends SignalBridgeException
{
  public function __construct(string $message = 'Insufficient permissions to perform this action')
  {
    parent::__construct($message, 403);
  }
}
