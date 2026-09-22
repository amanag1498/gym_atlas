<?php

namespace App\Http\Middleware;

use App\Services\Privacy\ConsentService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePurposeConsent
{
    public function __construct(private readonly ConsentService $consents) {}

    public function handle(Request $request, Closure $next, string $purpose): Response
    {
        $this->consents->assertGranted($request->user(), $purpose);

        return $next($request);
    }
}
