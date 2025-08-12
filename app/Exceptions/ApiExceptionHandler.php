<?php

namespace App\Exceptions;

use App\Exceptions\ClinicalException;
use App\Services\ClinicalLoggingService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * API Exception Handler
 * 
 * Provides consistent error response formatting for all API exceptions
 * in the EMR FHIR system.
 */
class ApiExceptionHandler
{
    protected ClinicalLoggingService $clinicalLogger;

    public function __construct(ClinicalLoggingService $clinicalLogger)
    {
        $this->clinicalLogger = $clinicalLogger;
    }

    /**
     * Render an exception into an HTTP response for API routes
     *
     * @param Request $request
     * @param Throwable $e
     * @return JsonResponse
     */
    public function render(Request $request, Throwable $e): JsonResponse
    {
        $traceId = Str::uuid()->toString();
        
        // Log the exception with comprehensive context
        $this->logException($e, $request, $traceId);

        return match (true) {
            $e instanceof \App\Exceptions\PatientSafetyException => $this->handleClinicalException($e, $traceId),
            $e instanceof \App\Exceptions\ClinicalWorkflowException => $this->handleClinicalException($e, $traceId),
            $e instanceof \App\Exceptions\DataIntegrityException => $this->handleClinicalException($e, $traceId),
            $e instanceof \App\Exceptions\BusinessRuleException => $this->handleClinicalException($e, $traceId),
            $e instanceof ClinicalException => $this->handleClinicalException($e, $traceId),
            $e instanceof ValidationException => $this->handleValidationException($e, $traceId),
            $e instanceof AuthenticationException => $this->handleAuthenticationException($e, $traceId),
            $e instanceof ModelNotFoundException => $this->handleModelNotFoundException($e, $traceId),
            $e instanceof NotFoundHttpException => $this->handleNotFoundHttpException($e, $traceId),
            $e instanceof MethodNotAllowedHttpException => $this->handleMethodNotAllowedHttpException($e, $traceId),
            $e instanceof HttpException => $this->handleHttpException($e, $traceId),
            default => $this->handleGenericException($e, $traceId),
        };
    }

    /**
     * Handle validation exceptions
     */
    private function handleValidationException(ValidationException $e, string $traceId): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'The given data was invalid.',
                'details' => $e->errors(),
            ],
            'meta' => [
                'timestamp' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
                'version' => 'v1',
                'trace_id' => $traceId,
            ]
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Handle authentication exceptions
     */
    private function handleAuthenticationException(AuthenticationException $e, string $traceId): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'UNAUTHORIZED',
                'message' => 'Authentication required.',
                'details' => null,
            ],
            'meta' => [
                'timestamp' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
                'version' => 'v1',
                'trace_id' => $traceId,
            ]
        ], Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Handle model not found exceptions
     */
    private function handleModelNotFoundException(ModelNotFoundException $e, string $traceId): JsonResponse
    {
        $model = class_basename($e->getModel());
        
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'NOT_FOUND',
                'message' => "{$model} not found.",
                'details' => null,
            ],
            'meta' => [
                'timestamp' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
                'version' => 'v1',
                'trace_id' => $traceId,
            ]
        ], Response::HTTP_NOT_FOUND);
    }

    /**
     * Handle not found HTTP exceptions
     */
    private function handleNotFoundHttpException(NotFoundHttpException $e, string $traceId): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'NOT_FOUND',
                'message' => 'The requested resource was not found.',
                'details' => null,
            ],
            'meta' => [
                'timestamp' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
                'version' => 'v1',
                'trace_id' => $traceId,
            ]
        ], Response::HTTP_NOT_FOUND);
    }

    /**
     * Handle method not allowed exceptions
     */
    private function handleMethodNotAllowedHttpException(MethodNotAllowedHttpException $e, string $traceId): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'METHOD_NOT_ALLOWED',
                'message' => 'The HTTP method is not allowed for this endpoint.',
                'details' => [
                    'allowed_methods' => $e->getHeaders()['Allow'] ?? null,
                ],
            ],
            'meta' => [
                'timestamp' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
                'version' => 'v1',
                'trace_id' => $traceId,
            ]
        ], Response::HTTP_METHOD_NOT_ALLOWED);
    }

    /**
     * Handle HTTP exceptions
     */
    private function handleHttpException(HttpException $e, string $traceId): JsonResponse
    {
        $statusCode = $e->getStatusCode();
        
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $this->getErrorCodeFromStatus($statusCode),
                'message' => $e->getMessage() ?: 'An HTTP error occurred.',
                'details' => null,
            ],
            'meta' => [
                'timestamp' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
                'version' => 'v1',
                'trace_id' => $traceId,
            ]
        ], $statusCode);
    }

    /**
     * Handle generic exceptions
     */
    private function handleGenericException(Throwable $e, string $traceId): JsonResponse
    {
        $message = app()->environment('production') 
            ? 'An internal server error occurred.' 
            : $e->getMessage();

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'INTERNAL_ERROR',
                'message' => $message,
                'details' => app()->environment('production') ? null : [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            ],
            'meta' => [
                'timestamp' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
                'version' => 'v1',
                'trace_id' => $traceId,
            ]
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Handle clinical exceptions
     */
    private function handleClinicalException(ClinicalException $e, string $traceId): JsonResponse
    {
        // Log clinical exception with additional context
        $this->clinicalLogger->logSecurityEvent(
            'Clinical Exception: ' . $e->getErrorCode(),
            'error',
            array_merge($e->getContext(), ['trace_id' => $traceId])
        );

        $statusCode = $e->getCode() ?: Response::HTTP_UNPROCESSABLE_ENTITY;
        
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
                'details' => $e->getContext(),
            ],
            'meta' => [
                'timestamp' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
                'version' => 'v1',
                'trace_id' => $traceId,
            ]
        ], $statusCode);
    }

    /**
     * Log exception with comprehensive context
     */
    private function logException(Throwable $e, Request $request, string $traceId): void
    {
        $context = [
            'trace_id' => $traceId,
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'user_id' => auth()->id(),
            'user_email' => auth()->user()?->email,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_data' => $this->sanitizeRequestData($request),
        ];

        // Log to appropriate channels based on exception type
        if ($e instanceof ClinicalException) {
            $this->clinicalLogger->logCriticalError('Clinical Exception', $e, $context);
        } elseif ($e instanceof AuthenticationException) {
            $this->clinicalLogger->logSecurityEvent('Authentication Exception', 'warning', $context);
        } else {
            logger()->error('API Exception', $context);
        }

        // Log API request details
        $this->clinicalLogger->logApiRequest(
            $request->method(),
            $request->path(),
            500, // Default to 500 for exceptions
            0, // Duration not available at this point
            ['trace_id' => $traceId, 'exception' => get_class($e)]
        );
    }

    /**
     * Sanitize request data for logging (remove sensitive information)
     */
    private function sanitizeRequestData(Request $request): array
    {
        $data = $request->all();
        
        // Remove sensitive fields
        $sensitiveFields = [
            'password',
            'password_confirmation',
            'token',
            'api_key',
            'secret',
            'credit_card',
            'ssn',
            'social_security_number',
        ];

        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '[REDACTED]';
            }
        }

        return $data;
    }

    /**
     * Get error code from HTTP status
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