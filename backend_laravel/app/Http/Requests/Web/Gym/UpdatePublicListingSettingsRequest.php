<?php

namespace App\Http\Requests\Web\Gym;

use App\Http\Requests\Gym\Admin\UpdateGymPublicListingSettingsRequest;

class UpdatePublicListingSettingsRequest extends UpdateGymPublicListingSettingsRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
    }
}
