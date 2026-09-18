<?php

namespace App\Traits;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A single response envelope for every driver API endpoint, so the mobile app
 * can read one shape everywhere:
 *
 *   { "status": true, "message": "...", "data": {...}, "errors": null }
 */
trait ApiResponse
{
    protected function ok(mixed $data = null, ?string $message = null, int $code = 200): JsonResponse
    {
        return response()->json([
            'status'  => true,
            'message' => $message,
            'data'    => $data,
            'errors'  => null,
        ], $code);
    }

    protected function created(mixed $data = null, ?string $message = null): JsonResponse
    {
        return $this->ok($data, $message, 201);
    }

    protected function fail(string $message, int $code = 400, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'status'  => false,
            'message' => $message,
            'data'    => null,
            'errors'  => $errors,
        ], $code);
    }

    protected function notFound(string $message = 'Resource not found.'): JsonResponse
    {
        return $this->fail($message, 404);
    }

    protected function forbidden(string $message = 'You do not have access to this resource.'): JsonResponse
    {
        return $this->fail($message, 403);
    }

    /**
     * Wrap a paginated result, keeping the page meta out of the data payload so
     * "data" is always the list itself and never an envelope-within-envelope.
     */
    protected function paginated(LengthAwarePaginator|ResourceCollection $paginator, ?string $message = null): JsonResponse
    {
        $resolved = $paginator instanceof ResourceCollection
            ? $paginator->resource
            : $paginator;

        $items = $paginator instanceof ResourceCollection
            ? $paginator->resolve()
            : $resolved->items();

        return response()->json([
            'status'  => true,
            'message' => $message,
            'data'    => $items,
            'meta'    => [
                'current_page' => $resolved->currentPage(),
                'last_page'    => $resolved->lastPage(),
                'per_page'     => $resolved->perPage(),
                'total'        => $resolved->total(),
                'has_more'     => $resolved->hasMorePages(),
            ],
            'errors'  => null,
        ]);
    }
}
