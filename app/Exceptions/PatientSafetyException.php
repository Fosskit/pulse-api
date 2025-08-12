<?php

namespace App\Exceptions;

/**
 * Patient Safety Exception
 * 
 * Thrown when operations could compromise patient safety,
 * such as medication conflicts, allergy violations, etc.
 */
class PatientSafetyException extends ClinicalException
{
    public function __construct(string $message, array $context = [], \Throwable $previous = null)
    {
        parent::__construct($message, 'PATIENT_SAFETY_VIOLATION', $context, 409, $previous);
    }
}