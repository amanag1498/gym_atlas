<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;

class PublicContextController extends Controller
{
    public function health()
    {
        return $this->success([
            'service' => config('app.name'),
            'timestamp' => now()->toIso8601String(),
        ], 'API is healthy.');
    }
}
