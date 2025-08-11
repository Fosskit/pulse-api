<?php

namespace App\Http\Controllers\V1;

use App\Actions\CreateServiceRequestAction;
use App\Actions\UpdateServiceResultsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\CreateServiceRequestRequest;
use App\Http\Requests\V1\UpdateServiceResultsRequest;
use App\Http\Resources\V1\ServiceRequestResource;
use App\Models\ServiceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;

class ServiceRequestController extends Controller
{
    public function __construct(
        private CreateServiceRequestAction $createServiceRequestAction,
        private UpdateServiceResultsAction $updateServiceResultsAction
    ) {}

    /**
     * Display a listing of service requests.
     */
    public function index(Request $request): JsonResponse
    {
        $serviceRequests = QueryBuilder::for(ServiceRequest::class)
            ->allowedFilters([
                'visit_id',
                'encounter_id',
                'request_type',
                'status_id',
                'completed_at',
            ])
            ->allowedSorts([
                'ordered_at',
                'completed_at',
                'created_at',
                'updated_at',
            ])
            ->allowedIncludes([
                'visit',
                'encounter',
                'service',
                'status',
                'laboratoryRequest',
                'laboratoryRequest.testConcept',
                'laboratoryRequest.specimenTypeConcept',
                'imagingRequest',
                'imagingRequest.modalityConcept',
                'imagingRequest.bodySiteConcept',
                'observations',
            ])
            ->defaultSort('-ordered_at')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => ServiceRequestResource::collection($serviceRequests->items()),
            'meta' => [
                'current_page' => $serviceRequests->currentPage(),
                'last_page' => $serviceRequests->lastPage(),
                'per_page' => $serviceRequests->perPage(),
                'total' => $serviceRequests->total(),
            ],
        ]);
    }

    /**
     * Store a newly created service request.
     */
    public function store(CreateServiceRequestRequest $request): JsonResponse
    {
        $serviceRequest = $this->createServiceRequestAction->execute($request->validated());

        return response()->json([
            'message' => 'Service request created successfully',
            'data' => new ServiceRequestResource($serviceRequest),
        ], 201);
    }

    /**
     * Display the specified service request.
     */
    public function show(ServiceRequest $serviceRequest): JsonResponse
    {
        $serviceRequest->load([
            'visit',
            'encounter',
            'service',
            'status',
            'laboratoryRequest.testConcept',
            'laboratoryRequest.specimenTypeConcept',
            'imagingRequest.modalityConcept',
            'imagingRequest.bodySiteConcept',
            'observations',
        ]);

        return response()->json([
            'data' => new ServiceRequestResource($serviceRequest),
        ]);
    }

    /**
     * Get pending service requests for a visit.
     */
    public function pending(Request $request): JsonResponse
    {
        $visitId = $request->get('visit_id');
        
        $query = ServiceRequest::pending();
        
        if ($visitId) {
            $query->where('visit_id', $visitId);
        }

        $serviceRequests = $query->with([
            'visit',
            'encounter',
            'service',
            'status',
            'laboratoryRequest.testConcept',
            'imagingRequest.modalityConcept',
        ])
        ->orderBy('ordered_at', 'desc')
        ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => ServiceRequestResource::collection($serviceRequests->items()),
            'meta' => [
                'current_page' => $serviceRequests->currentPage(),
                'last_page' => $serviceRequests->lastPage(),
                'per_page' => $serviceRequests->perPage(),
                'total' => $serviceRequests->total(),
            ],
        ]);
    }

    /**
     * Get service requests by type.
     */
    public function byType(Request $request, string $type): JsonResponse
    {
        $request->validate([
            'visit_id' => 'nullable|integer|exists:visits,id',
        ]);

        if (!in_array($type, ['Laboratory', 'Imaging', 'Procedure'])) {
            return response()->json([
                'error' => 'Invalid request type. Must be Laboratory, Imaging, or Procedure.',
            ], 400);
        }

        $query = ServiceRequest::byType($type);
        
        if ($request->get('visit_id')) {
            $query->where('visit_id', $request->get('visit_id'));
        }

        $serviceRequests = $query->with([
            'visit',
            'encounter',
            'service',
            'status',
            'laboratoryRequest.testConcept',
            'laboratoryRequest.specimenTypeConcept',
            'imagingRequest.modalityConcept',
            'imagingRequest.bodySiteConcept',
            'observations',
        ])
        ->orderBy('ordered_at', 'desc')
        ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => ServiceRequestResource::collection($serviceRequests->items()),
            'meta' => [
                'current_page' => $serviceRequests->currentPage(),
                'last_page' => $serviceRequests->lastPage(),
                'per_page' => $serviceRequests->perPage(),
                'total' => $serviceRequests->total(),
            ],
        ]);
    }

    /**
     * Update service request results and mark as completed.
     */
    public function updateResults(UpdateServiceResultsRequest $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $updatedServiceRequest = $this->updateServiceResultsAction->execute(
            $serviceRequest,
            $request->validated()
        );

        return response()->json([
            'message' => 'Service request results updated successfully',
            'data' => new ServiceRequestResource($updatedServiceRequest),
        ]);
    }

    /**
     * Mark service request as completed.
     */
    public function complete(ServiceRequest $serviceRequest): JsonResponse
    {
        $serviceRequest->update([
            'completed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Service request marked as completed',
            'data' => new ServiceRequestResource($serviceRequest->load([
                'visit',
                'encounter',
                'service',
                'status',
                'laboratoryRequest',
                'imagingRequest',
                'observations',
            ])),
        ]);
    }
}