<?php

namespace App\Support\Api;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success(mixed $data = null, string $message = 'Request successful.', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => null,
            'meta' => null,
        ], $status);
    }

    public static function successWithMeta(mixed $data = null, array $meta = [], string $message = 'Request successful.', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => null,
            'meta' => $meta,
        ], $status);
    }

    public static function paginated(LengthAwarePaginator $paginator, mixed $items, string $message = 'Request successful.'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $items,
            'errors' => null,
            'meta' => [
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'from' => $paginator->firstItem(),
                    'last_page' => $paginator->lastPage(),
                    'path' => $paginator->path(),
                    'per_page' => $paginator->perPage(),
                    'to' => $paginator->lastItem(),
                    'total' => $paginator->total(),
                ],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $errors
     */
    public static function error(string $message, int $status = 400, ?array $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => $errors,
            'meta' => null,
        ], $status);
    }

    /**
     * @param  array<string, mixed>  $errors
     */
    public static function validationError(array $errors, string $message = 'Validation failed.'): JsonResponse
    {
        return self::error($message, 422, $errors);
    }
}
