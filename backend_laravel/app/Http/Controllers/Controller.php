<?php

namespace App\Http\Controllers;

use App\Support\Api\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;

abstract class Controller
{
    use AuthorizesRequests;
    use ValidatesRequests;

    protected function success(mixed $data = null, string $message = 'Request successful.', int $status = 200): JsonResponse
    {
        return ApiResponse::success($data, $message, $status);
    }

    protected function successWithMeta(mixed $data = null, array $meta = [], string $message = 'Request successful.', int $status = 200): JsonResponse
    {
        return ApiResponse::successWithMeta($data, $meta, $message, $status);
    }

    protected function paginated(LengthAwarePaginator $paginator, mixed $items, string $message = 'Request successful.'): JsonResponse
    {
        return ApiResponse::paginated($paginator, $items, $message);
    }

    protected function error(string $message, int $status = 400, ?array $errors = null): JsonResponse
    {
        return ApiResponse::error($message, $status, $errors);
    }
}
