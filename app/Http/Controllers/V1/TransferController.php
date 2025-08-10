<?php

namespace App\Http\Controllers\V1;

use App\Actions\PatientTransferAction;
use App\Http\Controllers\V1\BaseController;
use App\Http\Requests\V1\TransferPatientRequest;
use App\Http\Requests\V1\ValidateTransferRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class TransferController extends BaseController
{
    /**
     * Transfer a patient to a different room/department.
     */
    public function transfer(TransferPatientRequest $request, PatientTransferAction $action): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            $result = $action->execute(
                $validated['visit_id'],
                $validated['destination_room_id'],
                $validated
            );

            return $this->successResponse(
                $result,
                'Patient transferred successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('Visit or destination room not found');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to transfer patient');
        }
    }

    /**
     * Validate a transfer request without executing it.
     */
    public function validateTransfer(ValidateTransferRequest $request, PatientTransferAction $action): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            $result = $action->validateTransfer(
                $validated['visit_id'],
                $validated['destination_room_id']
            );

            return $this->successResponse(
                $result,
                'Transfer validation completed'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to validate transfer');
        }
    }
}