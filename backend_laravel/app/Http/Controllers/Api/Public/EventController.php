<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Services\Events\EventService;

class EventController extends Controller
{
    public function __construct(private readonly EventService $events) {}

    public function show(string $publicToken)
    {
        return $this->success(EventResource::make($this->events->publicEvent($publicToken)));
    }
}
