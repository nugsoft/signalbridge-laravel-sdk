<?php

namespace Nugsoft\SignalBridge\Exceptions;

class UnauthorizedException extends SignalBridgeException
{
    public function __construct(string $message = 'Unauthorized. Check your API token.')
    {
        parent::__construct($message, 401);
    }
}
