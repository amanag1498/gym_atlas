<?php

namespace App\Services\Web;

use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvStreamService
{
    /**
     * @param  list<string>  $columns
     * @param  iterable<int, array<int, scalar|null>>  $rows
     */
    public function download(string $filename, array $columns, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($columns, $rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
