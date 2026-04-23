<?php

namespace Nugsoft\SignalBridge\Exceptions;

class RateLimitedException extends SignalBridgeException
{
    public function __construct(string $message = 'Too many requests. Please slow down.')
    {
        parent::__construct($message, 429);
    }
}
