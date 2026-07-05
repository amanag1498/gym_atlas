<?php

namespace App\Http\Resources\Discovery;

use App\Support\Profiles\TrainerProfilePresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicTrainerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return TrainerProfilePresenter::present($this->resource, $this->user, [
            'public' => true,
            'include_client_count' => false,
            'contact_enabled' => true,
            'contact_mode' => 'gym_contact',
            'contact_label' => 'Contact via gym',
            'request_enabled' => true,
            'request_mode' => 'trial_request_via_gym',
            'request_label' => 'Request via gym',
        ]) ?? [];
    }
}
