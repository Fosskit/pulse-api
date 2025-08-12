<?php

namespace App\Http\Controllers\V1;

use App\Actions\ExportVisitAction;
use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    public function __construct(
        private ExportVisitAction $exportVisitAction
    ) {}

    /**
     * Export a single visit with comprehensive data
     */
    public function exportVisit(int $visitId): JsonResponse
    {
        try {
            $exportData = $this->exportVisitAction->execute($visitId);
            
            return response()->json($exportData);
        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'code' => 'EXPORT_ERROR',
                    'message' => 'Failed to export visit data',
                    'details' => $e->getMessage(),
                    'trace_id' => uniqid()
                ]
            ], 500);
        }
    }

    /**
     * Export all visits for a patient
     */
    public function exportPatientVisits(int $patientId): JsonResponse
    {
        try {
            $patient = Patient::findOrFail($patientId);
            $visits = $patient->visits()->pluck('id');
            
            $allVisits = [];
            foreach ($visits as $visitId) {
                $visitData = $this->exportVisitAction->execute($visitId);
                $allVisits = array_merge($allVisits, $visitData['visits']);
            }
            
            return response()->json([
                'visits' => $allVisits
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'code' => 'EXPORT_ERROR',
                    'message' => 'Failed to export patient visits',
                    'details' => $e->getMessage(),
                    'trace_id' => uniqid()
                ]
            ], 500);
        }
    }

    /**
     * Bulk export multiple visits
     */
    public function bulkExport(Request $request): JsonResponse
    {
        $request->validate([
            'visit_ids' => 'required|array',
            'visit_ids.*' => 'integer|exists:visits,id'
        ]);

        try {
            $allVisits = [];
            
            foreach ($request->visit_ids as $visitId) {
                $visitData = $this->exportVisitAction->execute($visitId);
                $allVisits = array_merge($allVisits, $visitData['visits']);
            }
            
            return response()->json([
                'visits' => $allVisits
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'code' => 'BULK_EXPORT_ERROR',
                    'message' => 'Failed to bulk export visits',
                    'details' => $e->getMessage(),
                    'trace_id' => uniqid()
                ]
            ], 500);
        }
    }
}