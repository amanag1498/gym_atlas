<?php

namespace App\Http\Requests\Concerns;

trait HasSafeUploadRules
{
    /**
     * @return array<int, string>
     */
    protected function safeCsvFileRules(int $maxKilobytes = 4096): array
    {
        return [
            'required',
            'file',
            'mimes:csv,txt',
            'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel',
            'max:'.$maxKilobytes,
        ];
    }
}
