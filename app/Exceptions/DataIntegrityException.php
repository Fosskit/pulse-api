<?php

namespace App\Exceptions;

/**
 * Data Integrity Exception
 * 
 * Thrown when data integrity constraints are violated,
 * such as referential integrity, business rule violations, etc.
 */
class DataIntegrityException extends ClinicalException
{
    public function __construct(string $message, array $context = [], \Throwable $previous = null)
    {
        parent::__construct($message, 'DATA_INTEGRITY_ERROR', $context, 422, $previous);
    }
}