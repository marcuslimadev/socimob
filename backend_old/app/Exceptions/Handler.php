<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        AuthorizationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        ValidationException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Throwable  $exception
     * @return void
     *
     * @throws \Exception
     */
    public function report(Throwable $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $exception)
    {
        // Se for requisição JSON/API, retornar erro em JSON
        if ($request->expectsJson() || $request->is('api/*') || $request->is('debug/*')) {
            $status = 500;
            
            if ($exception instanceof HttpException) {
                $status = $exception->getStatusCode();
            } elseif ($exception instanceof ModelNotFoundException) {
                $status = 404;
            } elseif ($exception instanceof ValidationException) {
                $status = 422;
            } elseif ($exception instanceof AuthorizationException) {
                $status = 403;
            }
            
            $response = [
                'success' => false,
                'error' => $exception->getMessage(),
                'exception' => get_class($exception),
                'status' => $status
            ];
            
            // Adicionar trace se debug mode ativo
            if (env('APP_DEBUG', false)) {
                $response['file'] = $exception->getFile();
                $response['line'] = $exception->getLine();
                $response['trace'] = collect($exception->getTrace())->map(function ($trace) {
                    return [
                        'file' => $trace['file'] ?? null,
                        'line' => $trace['line'] ?? null,
                        'function' => $trace['function'] ?? null,
                        'class' => $trace['class'] ?? null
                    ];
                })->take(10)->toArray();
            }
            
            return response()->json($response, $status);
        }
        
        return parent::render($request, $exception);
    }
}
