<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Throwable;
use App\Traits\ApiResponse;

class Handler extends ExceptionHandler
{
    use ApiResponse;

    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $exception)
    {
        // Handle API requests
        if ($request->is('api/*') || $request->expectsJson()) {
            return $this->renderJsonResponse($request, $exception);
        }

        return parent::render($request, $exception);
    }

    /**
     * Render JSON response for API exceptions
     */
    protected function renderJsonResponse($request, Throwable $exception)
    {
        // Validation errors
        if ($exception instanceof ValidationException) {
            return $this->error(
                collect($exception->errors())->first()[0],
                [],
                422
            );
        }

        // Authentication errors
        if ($exception instanceof AuthenticationException) {
            return $this->error('Unauthenticated.', [], 401);
        }

        // Authorization errors
        if ($exception instanceof AuthorizationException) {
            return $this->error('This action is unauthorized.', [], 403);
        }

        // Model not found
        if ($exception instanceof ModelNotFoundException) {
            return $this->notFound('Resource not found.');
        }

        // Route not found
        if ($exception instanceof NotFoundHttpException) {
            return $this->notFound('API endpoint not found.');
        }

        // Method not allowed
        if ($exception instanceof MethodNotAllowedHttpException) {
            return $this->error('Method not allowed.', [], 405);
        }

        // Model not found
        if ($exception instanceof ModelNotFoundException) {

            $model = class_basename($exception->getModel());

            $messages = [
                'Plan' => 'Plan not found',
                'User' => 'User not found',
            ];

            return $this->notFound(
                $messages[$model] ?? 'Resource not found'
            );
        }

        // Generic server error for unexpected exceptions
        return $this->error(
            'Something went wrong.',
            config('app.debug') ? ['exception' => $exception->getMessage()] : [],
            500
        );
    }
}
