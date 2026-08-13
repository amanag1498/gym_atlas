<?php

namespace App\Http\Requests\Event;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scope' => ['sometimes', Rule::in(['global', 'gym'])],
            'gym_id' => ['nullable', 'integer', 'exists:gyms,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'host_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:10000'],
            'cover_image_url' => ['nullable', 'url', 'max:2048'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'timezone' => ['required', 'timezone:all'],
            'booking_opens_at' => ['nullable', 'date', 'before:ends_at'],
            'booking_closes_at' => ['nullable', 'date', 'after_or_equal:booking_opens_at', 'before_or_equal:starts_at'],
            'cancellation_closes_at' => ['nullable', 'date', 'before_or_equal:starts_at'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'waitlist_enabled' => ['sometimes', 'boolean'],
            'pricing_type' => ['required', Rule::in(['free', 'pay_at_venue'])],
            'price_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'required_if:pricing_type,pay_at_venue'],
            'currency' => ['nullable', 'string', 'size:3'],
            'payment_note' => ['nullable', 'string', 'max:500'],
            'location_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'status' => ['sometimes', Rule::in(['draft', 'published'])],
        ];
    }
}
