<?php

namespace App\Exceptions;

/**
 * Clinical Workflow Exception
 * 
 * Thrown when clinical workflow rules are violated,
 * such as invalid state transitions, missing prerequisites, etc.
 */
class ClinicalWorkflowException extends ClinicalException
{
    public function __construct(string $message, array $context = [], \Throwable $previous = null)
    {
        parent::__construct($message, 'CLINICAL_WORKFLOW_ERROR', $context, 422, $previous);
    }
}