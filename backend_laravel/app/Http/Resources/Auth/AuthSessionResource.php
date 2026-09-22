<?php

namespace App\Http\Resources\Auth;

use App\Http\Resources\User\UserResource;
use App\Services\Privacy\ConsentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->resource['token'],
            'token_type' => 'Bearer',
            'user' => UserResource::make($this->resource['user']),
            'consent_state' => app(ConsentService::class)->state($this->resource['user']),
        ];
    }
}
