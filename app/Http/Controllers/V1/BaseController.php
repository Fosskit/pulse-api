<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Base controller for all V1 API endpoints
 * 
 * Provides standardized response methods for consistent API responses
 * across all endpoints in the EMR FHIR system.
 */
abstract class BaseController extends Controller
{
    /**
     * Return a successful JSON response
     *
     * @param mixed $data The data to return
     * @param string $message Success message
     * @param int $status HTTP status code
     * @return JsonResponse
     */
    protected function successResponse($data = null, string $message = 'Success', int $status = Response::HTTP_OK): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => [
                'timestamp' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
                'version' => 'v1',
                'trace_id' => $this->generateTraceId(),
            ]
        ];

        // Add user context if authenticated
        if (auth()->check()) {
            $response['meta']['user_id'] = auth()->id();
        }

        return response()->json($response, $status);
    }

    /**
     * Return an error JSON response
     *
     * @param string $message Error message
     * @param int $status HTTP status code
     * @param mixed $errors Detailed error information
     * @param string|null $code Error code for client handling
     * @return JsonResponse
     */
    protected function errorResponse(
        string $message = 'An error occurred', 
        int $status = Response::HTTP_BAD_REQUEST, 
        $errors = null,
        ?string $code = null
    ): JsonResponse {
        $response = [
            'success' => false,
            'error' => [
                'code' => $code ?? $this->getErrorCodeFromStatus($status),
                'message' => $message,
                'details' => $errors,
            ],
            'meta' => [
                'timestamp' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
                'version' => 'v1',
                'trace_id' => $this->generateTraceId(),
            ]
        ];

        // Add user context if authenticated
        if (auth()->check()) {
            $response['meta']['user_id'] = auth()->id();
        }

        return response()->json($response, $status);
    }

    /**
     * Return a paginated JSON response
     *
     * @param mixed $data Paginated data
     * @param string $message Success message
     * @return JsonResponse
     */
    protected function paginatedResponse($data, string $message = 'Success'): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data->items(),
            'pagination' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
                'has_more' => $data->hasMorePages(),
                'links' => [
                    'first' => $data->url(1),
                    'last' => $data->url($data->lastPage()),
                    'prev' => $data->previousPageUrl(),
                    'next' => $data->nextPageUrl(),
                ]
            ],
            'meta' => [
                'timestamp' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
                'version' => 'v1',
                'trace_id' => $this->generateTraceId(),
            ]
        ];

        // Add user context if authenticated
        if (auth()->check()) {
            $response['meta']['user_id'] = auth()->id();
        }

        return response()->json($response);
    }

    /**
     * Return a created resource response
     *
     * @param mixed $data The created resource data
     * @param string $message Success message
     * @return JsonResponse
     */
    protected function createdResponse($data, string $message = 'Resource created successfully'): JsonResponse
    {
        return $this->successResponse($data, $message, Response::HTTP_CREATED);
    }

    /**
     * Return a no content response
     *
     * @param string $message Success message
     * @return JsonResponse
     */
    protected function noContentResponse(string $message = 'Operation completed successfully'): JsonResponse
    {
        return $this->successResponse(null, $message, Response::HTTP_NO_CONTENT);
    }

    /**
     * Return a validation error response
     *
     * @param array $errors Validation errors
     * @param string $message Error message
     * @return JsonResponse
     */
    protected function validationErrorResponse(array $errors, string $message = 'The given data was invalid.'): JsonResponse
    {
        return $this->errorResponse($message, Response::HTTP_UNPROCESSABLE_ENTITY, $errors, 'VALIDATION_ERROR');
    }

    /**
     * Return a not found error response
     *
     * @param string $message Error message
     * @return JsonResponse
     */
    protected function notFoundResponse(string $message = 'Resource not found'): JsonResponse
    {
        return $this->errorResponse($message, Response::HTTP_NOT_FOUND, null, 'NOT_FOUND');
    }

    /**
     * Return an unauthorized error response
     *
     * @param string $message Error message
     * @return JsonResponse
     */
    protected function unauthorizedResponse(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->errorResponse($message, Response::HTTP_UNAUTHORIZED, null, 'UNAUTHORIZED');
    }

    /**
     * Return a forbidden error response
     *
     * @param string $message Error message
     * @return JsonResponse
     */
    protected function forbiddenResponse(string $message = 'Forbidden'): JsonResponse
    {
        return $this->errorResponse($message, Response::HTTP_FORBIDDEN, null, 'FORBIDDEN');
    }

    /**
     * Return a conflict error response
     *
     * @param string $message Error message
     * @param mixed $details Additional error details
     * @return JsonResponse
     */
    protected function conflictResponse(string $message = 'Conflict', $details = null): JsonResponse
    {
        return $this->errorResponse($message, Response::HTTP_CONFLICT, $details, 'CONFLICT');
    }

    /**
     * Return an internal server error response
     *
     * @param string $message Error message
     * @return JsonResponse
     */
    protected function serverErrorResponse(string $message = 'Internal server error'): JsonResponse
    {
        return $this->errorResponse($message, Response::HTTP_INTERNAL_SERVER_ERROR, null, 'INTERNAL_ERROR');
    }

    /**
     * Generate a unique trace ID for request tracking
     *
     * @return string
     */
    private function generateTraceId(): string
    {
        return Str::uuid()->toString();
    }

    /**
     * Get error code from HTTP status
     *
     * @param int $status HTTP status code
     * @return string
     */
    private function getErrorCodeFromStatus(int $status): string
    {
        return match($status) {
            Response::HTTP_BAD_REQUEST => 'BAD_REQUEST',
            Response::HTTP_UNAUTHORIZED => 'UNAUTHORIZED',
            Response::HTTP_FORBIDDEN => 'FORBIDDEN',
            Response::HTTP_NOT_FOUND => 'NOT_FOUND',
            Response::HTTP_METHOD_NOT_ALLOWED => 'METHOD_NOT_ALLOWED',
            Response::HTTP_CONFLICT => 'CONFLICT',
            Response::HTTP_UNPROCESSABLE_ENTITY => 'VALIDATION_ERROR',
            Response::HTTP_TOO_MANY_REQUESTS => 'RATE_LIMIT_EXCEEDED',
            Response::HTTP_INTERNAL_SERVER_ERROR => 'INTERNAL_ERROR',
            default => 'UNKNOWN_ERROR',
        };
    }
}
