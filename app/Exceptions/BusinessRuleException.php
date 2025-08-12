<?php

namespace App\Exceptions;

/**
 * Business Rule Exception
 * 
 * Thrown when business rules are violated,
 * such as billing rules, insurance validation, etc.
 */
class BusinessRuleException extends ClinicalException
{
    public function __construct(string $message, array $context = [], \Throwable $previous = null)
    {
        parent::__construct($message, 'BUSINESS_RULE_VIOLATION', $context, 422, $previous);
    }
}