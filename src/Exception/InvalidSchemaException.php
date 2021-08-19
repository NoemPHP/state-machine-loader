<?php

declare(strict_types=1);

namespace Noem\State\Loader\Exception;

class InvalidSchemaException extends LoaderException
{
    public function __construct(private array $errors = [], \Throwable $previous = null)
    {
        $message = 'Invalid schema!' . PHP_EOL . print_r($this->errors, true);
        parent::__construct($message, 0, $previous);
    }

    public function getErrorReport(): array
    {
        return $this->errors;
    }
}
