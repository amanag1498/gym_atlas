<?php

namespace App\Http\Requests\Diet;

use Illuminate\Foundation\Http\FormRequest;

class SaveFoodCatalogItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'dietary_tags' => $this->stringList('dietary_tags'),
            'allergens' => $this->stringList('allergens'),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:120'],
            'default_quantity' => ['nullable', 'string', 'max:120'],
            'serving_size_g' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'calories' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'protein_g' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'carbs_g' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'fats_g' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'fiber_g' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'dietary_tags' => ['nullable', 'array', 'max:30'],
            'dietary_tags.*' => ['string', 'max:80'],
            'allergens' => ['nullable', 'array', 'max:30'],
            'allergens.*' => ['string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    private function stringList(string $key): array
    {
        $value = $this->input($key, []);
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        return collect(is_array($value) ? $value : [])
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->unique(fn ($item) => mb_strtolower($item))
            ->values()
            ->all();
    }
}
