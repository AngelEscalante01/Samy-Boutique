<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class ApiController extends Controller
{
    protected function responseMeta(): array
    {
        $requestId = request()?->attributes->get('request_id');

        return [
            'request_id' => $requestId,
        ];
    }

    protected function successResponse(mixed $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => [],
            'meta' => $this->responseMeta(),
        ], $status);
    }

    protected function errorResponse(string $message = 'Error en la solicitud.', array $errors = [], int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => $errors,
            'meta' => $this->responseMeta(),
        ], $status);
    }

    protected function paginatedResponse(LengthAwarePaginator $paginator, array $items, string $message = 'OK'): JsonResponse
    {
        return $this->successResponse([
            'items' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'has_more_pages' => $paginator->hasMorePages(),
            ],
        ], $message);
    }

    protected function ensurePermission(Request $request, string $permission): void
    {
        abort_unless(
            $request->user() !== null && $request->user()->can($permission),
            403,
            'No autorizado para este recurso.'
        );
    }

    protected function ensureAnyPermission(Request $request, array $permissions): void
    {
        $user = $request->user();

        abort_unless(
            $user !== null && collect($permissions)->contains(fn (string $permission) => $user->can($permission)),
            403,
            'No autorizado para este recurso.'
        );
    }
}
