<?php

namespace App\Http\Requests\Communication;

use App\Enums\AnnouncementAudienceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'gym_id' => ['nullable', 'integer', 'exists:gyms,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'audience_type' => ['required', 'in:'.implode(',', AnnouncementAudienceType::values())],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'exists:users,id'],
            'send_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $audience = $this->input('audience_type');

            if ($audience === AnnouncementAudienceType::BranchSpecific->value && ! $this->filled('branch_id')) {
                $validator->errors()->add('branch_id', 'A branch is required for branch specific announcements.');
            }

            if (in_array($audience, [
                AnnouncementAudienceType::SelectedMembers->value,
                AnnouncementAudienceType::TrainerAssignment->value,
            ], true) && empty($this->input('member_ids', []))) {
                $validator->errors()->add('member_ids', 'At least one member is required for this announcement type.');
            }
        });
    }
}
