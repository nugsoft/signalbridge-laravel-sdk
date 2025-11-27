<?php

namespace Nugsoft\SignalBridge\Exceptions;

class ValidationException extends SignalBridgeException
{
  protected array $errors = [];

  public function __construct(string $message = 'Validation error', array $errors = [], array $data = [])
  {
    parent::__construct($message, 422, $data);
    $this->errors = $errors;
  }

  public function getErrors(): array
  {
    return $this->errors;
  }

  public function getFirstError(): ?string
  {
    $firstField = array_key_first($this->errors);
    return $firstField ? ($this->errors[$firstField][0] ?? null) : null;
  }
}
