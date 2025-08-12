<?php

namespace App\Traits;

use App\Services\ClinicalLoggingService;

/**
 * Logs Clinical Activity Trait
 * 
 * Provides logging capabilities for Action classes to track
 * clinical operations and maintain audit trails.
 */
trait LogsClinicalActivity
{
    protected function getClinicalLogger(): ClinicalLoggingService
    {
        return app(ClinicalLoggingService::class);
    }

    /**
     * Log the start of an action
     */
    protected function logActionStart(string $action, array $context = []): void
    {
        $this->getClinicalLogger()->logClinicalActivity(
            $this->getActionCategory(),
            "start_{$action}",
            array_merge($context, [
                'action_class' => static::class,
                'started_at' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
            ])
        );
    }

    /**
     * Log the completion of an action
     */
    protected function logActionComplete(string $action, array $context = []): void
    {
        $this->getClinicalLogger()->logClinicalActivity(
            $this->getActionCategory(),
            "complete_{$action}",
            array_merge($context, [
                'action_class' => static::class,
                'completed_at' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
            ])
        );
    }

    /**
     * Log an action failure
     */
    protected function logActionFailure(string $action, \Throwable $exception, array $context = []): void
    {
        $this->getClinicalLogger()->logCriticalError(
            "Action failed: {$action}",
            $exception,
            array_merge($context, [
                'action_class' => static::class,
                'failed_at' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
            ])
        );
    }

    /**
     * Log patient-related activity
     */
    protected function logPatientActivity(string $action, int $patientId, array $context = []): void
    {
        $this->getClinicalLogger()->logPatientActivity(
            $action,
            array_merge($context, [
                'patient_id' => $patientId,
                'action_class' => static::class,
            ])
        );
    }

    /**
     * Log visit-related activity
     */
    protected function logVisitActivity(string $action, int $visitId, array $context = []): void
    {
        $this->getClinicalLogger()->logVisitActivity(
            $action,
            array_merge($context, [
                'visit_id' => $visitId,
                'action_class' => static::class,
            ])
        );
    }

    /**
     * Log encounter-related activity
     */
    protected function logEncounterActivity(string $action, int $encounterId, array $context = []): void
    {
        $this->getClinicalLogger()->logEncounterActivity(
            $action,
            array_merge($context, [
                'encounter_id' => $encounterId,
                'action_class' => static::class,
            ])
        );
    }

    /**
     * Log medication-related activity
     */
    protected function logMedicationActivity(string $action, array $context = []): void
    {
        $this->getClinicalLogger()->logMedicationActivity(
            $action,
            array_merge($context, [
                'action_class' => static::class,
            ])
        );
    }

    /**
     * Log service request activity
     */
    protected function logServiceRequestActivity(string $action, array $context = []): void
    {
        $this->getClinicalLogger()->logServiceRequestActivity(
            $action,
            array_merge($context, [
                'action_class' => static::class,
            ])
        );
    }

    /**
     * Log billing activity
     */
    protected function logBillingActivity(string $action, array $context = []): void
    {
        $this->getClinicalLogger()->logBillingActivity(
            $action,
            array_merge($context, [
                'action_class' => static::class,
            ])
        );
    }

    /**
     * Log audit trail for data modifications
     */
    protected function logAuditTrail(string $action, string $resource, array $context = []): void
    {
        $this->getClinicalLogger()->logAuditTrail(
            $action,
            $resource,
            array_merge($context, [
                'action_class' => static::class,
            ])
        );
    }

    /**
     * Get the action category based on class name
     */
    protected function getActionCategory(): string
    {
        $className = class_basename(static::class);
        
        // Extract category from action class name
        if (str_contains($className, 'Patient')) {
            return 'patient';
        } elseif (str_contains($className, 'Visit')) {
            return 'visit';
        } elseif (str_contains($className, 'Encounter')) {
            return 'encounter';
        } elseif (str_contains($className, 'Medication')) {
            return 'medication';
        } elseif (str_contains($className, 'Service')) {
            return 'service_request';
        } elseif (str_contains($className, 'Invoice') || str_contains($className, 'Billing') || str_contains($className, 'Payment')) {
            return 'billing';
        } elseif (str_contains($className, 'Form')) {
            return 'clinical_form';
        } else {
            return 'general';
        }
    }
}