<?php

namespace App\Http\Requests\Web\Gym;

trait InteractsWithDelimitedFields
{
    /**
     * @return list<string>
     */
    protected function parseDelimitedString(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $item): string => trim($item),
            preg_split('/[\r\n,]+/', $value) ?: [],
        )));
    }

    protected function parseJsonArray(?string $value): array|string|null
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : '__invalid_json__';
    }
}
