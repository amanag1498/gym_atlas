<?php

namespace App\Http\Requests\Web\Gym;

use App\Http\Requests\Concerns\HasSafeUploadRules;
use Illuminate\Foundation\Http\FormRequest;

class PreviewMemberImportRequest extends FormRequest
{
    use HasSafeUploadRules;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'members_csv' => $this->safeCsvFileRules(),
        ];
    }
}
