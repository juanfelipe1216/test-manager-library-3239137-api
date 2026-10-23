<?php

use App\Application\Services\LoginService;
use App\Http\Controllers\LibraryController;
use App\Http\Middleware\ApiAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

Route::post('/auth/login', function (
    Request $request,
    LoginService $login,
) {
    $validator = Validator::make(
        $request->all(),
        [
            'username' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string'],
        ],
    );

    if ($validator->fails()) {
        $details = [];

        foreach ($validator->errors()->messages() as $field => $messages) {
            $details[] = [
                'field' => $field,
                'issue' => 'Campo obligatorio o formato inválido.',
            ];
        }

        return response()->json([
            'code' => 'VALIDATION_ERROR',
            'message' => 'Revisa los datos enviados.',
            'details' => $details,
        ], 422);
    }

    try {
        return response()->json($login->execute(
            $request->input('username'),
            $request->input('password'),
        ));
    } catch (DomainException) {
        return response()->json([
            'code' => 'INVALID_CREDENTIALS',
            'message' => 'Credenciales inválidas.',
            'details' => [],
        ], 401);
    }
});
Route::middleware(ApiAuth::class)->group(function () {
    $controller = LibraryController::class;
    Route::get('/categories', [$controller, 'categories']);
    Route::get('/books', [$controller, 'books']);
    Route::get('/books/{id}', [$controller, 'book']);
    Route::get('/books/{id}/cover', [$controller, 'cover']);
    Route::get('/members', [$controller, 'members']);
    Route::get('/members/{id}', [$controller, 'member']);
    Route::post('/members', [$controller, 'createMember']);
    Route::get('/loans/overdue', [$controller, 'overdue']);
    Route::get('/loans', [$controller, 'loans']);
    Route::get('/loans/{id}', [$controller, 'loan']);
    Route::post('/loans', [$controller, 'lend']);
    Route::post('/loans/{loanId}/items/{itemId}/return', [$controller, 'returnItem']);
    Route::middleware(ApiAuth::class.':admin')->group(function () use ($controller) {
        Route::post('/users', [$controller, 'createUser']);
        Route::post('/books', [$controller, 'createBook']);
        Route::put('/books/{id}', [$controller, 'updateBook']);
        Route::delete('/books/{id}', [$controller, 'deleteBook']);
        Route::put('/books/{id}/cover', [$controller, 'setCover']);
        Route::delete('/books/{id}/cover', [$controller, 'deleteCover']);
        Route::get('/reports/most-borrowed',[$controller, 'report']);
    });
});
