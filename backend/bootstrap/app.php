<?php

use App\Application\BusinessError;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', api: __DIR__.'/../routes/api.php', commands: __DIR__.'/../routes/console.php', health: '/up', apiPrefix: 'api/v1')
    ->withMiddleware(function (Middleware $middleware): void {})
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }
            $code = 'INTERNAL_ERROR';
            $message = 'No se pudo completar la operación.';
            $status = 500;
            $details = [];
            if ($e instanceof BusinessError) {
                $code = $e->errorCode;
                $message = $e->getMessage();
                $status = $e->status;
                $details = $e->details;
            } elseif ($e instanceof ValidationException) {
                $code = 'VALIDATION_ERROR';
                $message = 'Revisa los datos enviados.';
                $status = 422;
                foreach ($e->errors() as $field => $issues) {
                    $details[] = ['field' => $field, 'issue' => 'Campo obligatorio o formato inválido.'];
                }
            } elseif ($e instanceof QueryException) {
                if (($e->errorInfo[0] ?? '') === '23505') {
                    $raw = $e->getMessage();
                    $code = str_contains($raw, 'username') ? 'USERNAME_TAKEN' : (str_contains($raw, 'document_number') ? 'DOCUMENT_TAKEN' : 'ISBN_TAKEN');
                    $message = 'El registro ya existe.';
                    $status = 409;
                } elseif (in_array($e->errorInfo[0] ?? '', ['23503', '23514', '22P02', '22001'], true)) {
                    $code = 'VALIDATION_ERROR';
                    $message = 'Los datos no cumplen las restricciones.';
                    $status = 422;
                } elseif (in_array($e->errorInfo[0] ?? '', ['40001', '40P01'], true)) {
                    $code = 'BOOK_NOT_AVAILABLE';
                    $message = 'La operación entró en conflicto; inténtalo de nuevo.';
                    $status = 409;
                }
            } elseif ($e instanceof DomainException || $e instanceof InvalidArgumentException) {
                $code = 'VALIDATION_ERROR';
                $message = $e->getMessage();
                $status = 422;
            } elseif ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();
                $code = $status === 404 ? 'NOT_FOUND' : 'HTTP_ERROR';
                $message = $status === 404 ? 'Recurso no encontrado.' : 'La operación no está permitida.';
            }

            return response()->json(compact('code', 'message', 'details'), $status);
        });
        // Database errors can contain SQL bindings; do not write them to logs.
        $exceptions->dontReport([QueryException::class]);
    })->create();
