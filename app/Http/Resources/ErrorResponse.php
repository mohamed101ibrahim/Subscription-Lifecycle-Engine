<?php

namespace App\Http\Resources;

use Illuminate\Validation\ValidationException;

class ErrorResponse
{
    /**
     * Create an error response
     */
    public static function make(
        string $message = 'An error occurred',
        ?array $errors = null,
        string $code = 'ERROR',
        int $statusCode = 400
    ): array {
        $response = [
            'success' => false,
            'message' => $message,
            'code' => $code,
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        return $response;
    }

    /**
     * Create validation error response
     */
    public static function validation(array $errors, string $message = 'Validation failed'): array
    {
        return [
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'code' => 'VALIDATION_ERROR',
        ];
    }

    /**
     * Create not found error
     */
    public static function notFound(string $resource = 'Resource'): array
    {
        return [
            'success' => false,
            'message' => "{$resource} not found",
            'code' => 'NOT_FOUND',
        ];
    }

    /**
     * Create unauthorized error
     */
    public static function unauthorized(string $message = 'Unauthorized'): array
    {
        return [
            'success' => false,
            'message' => $message,
            'code' => 'UNAUTHORIZED',
        ];
    }

    /**
     * Create forbidden error
     */
    public static function forbidden(string $message = 'Forbidden'): array
    {
        return [
            'success' => false,
            'message' => $message,
            'code' => 'FORBIDDEN',
        ];
    }

    /**
     * Create conflict error
     */
    public static function conflict(string $message = 'Resource conflict'): array
    {
        return [
            'success' => false,
            'message' => $message,
            'code' => 'CONFLICT',
        ];
    }

    /**
     * Create invalid transition error
     */
    public static function invalidTransition(string $from, string $to): array
    {
        return [
            'success' => false,
            'message' => "Cannot transition from {$from} to {$to}",
            'code' => 'INVALID_TRANSITION',
        ];
    }

    /**
     * Create server error
     */
    public static function serverError(string $message = 'Internal server error', ?string $error = null): array
    {
        $response = [
            'success' => false,
            'message' => $message,
            'code' => 'SERVER_ERROR',
        ];

        if ($error && config('app.debug')) {
            $response['error'] = $error;
        }

        return $response;
    }

    /**
     * Create quota exceeded error
     */
    public static function quotaExceeded(string $message = 'Quota exceeded'): array
    {
        return [
            'success' => false,
            'message' => $message,
            'code' => 'QUOTA_EXCEEDED',
        ];
    }
}
