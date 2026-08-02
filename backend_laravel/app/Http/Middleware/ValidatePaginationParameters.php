<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidatePaginationParameters
{
    public function handle(Request $request, Closure $next): Response
    {
        $rules = [];

        if ($request->query->has('per_page')) {
            $rules['per_page'] = ['nullable', 'integer', 'min:1', 'max:100'];
        }

        foreach ($request->query() as $key => $value) {
            if ($key !== 'per_page' && ($key === 'page' || str_ends_with((string) $key, '_page'))) {
                $rules[$key] = ['nullable', 'integer', 'min:1'];
            }
        }

        if ($rules !== []) {
            $request->validate($rules);
        }

        return $next($request);
    }
}
