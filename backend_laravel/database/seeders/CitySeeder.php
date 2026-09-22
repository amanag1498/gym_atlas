<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use JsonException;

class CitySeeder extends Seeder
{
    /**
     * Replace the city catalog with the supplied India city list. The source
     * contains four duplicate name/state pairs; the first ID wins because the
     * cities table requires each name/state/country combination to be unique.
     */
    public function run(): void
    {
        $path = database_path('data/cities.json');
        $json = file_get_contents($path);

        if ($json === false) {
            throw new InvalidArgumentException("Cannot read city catalog: {$path}");
        }

        try {
            $source = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('City catalog is not valid JSON.', previous: $exception);
        }

        if (! is_array($source) || ! array_is_list($source) || $source === []) {
            throw new InvalidArgumentException('City catalog must be a non-empty JSON array.');
        }

        $cities = [];
        $newIdsByPlace = [];
        $seenIds = [];
        $now = now();

        foreach ($source as $index => $item) {
            if (! is_array($item)
                || ! isset($item['id'], $item['name'], $item['state'])
                || ! is_string($item['id'])
                || ! ctype_digit($item['id'])
                || (int) $item['id'] < 1
                || ! is_string($item['name'])
                || ! is_string($item['state'])
                || trim($item['name']) === ''
                || trim($item['state']) === ''
                || mb_strlen($item['name']) > 255
                || mb_strlen($item['state']) > 255) {
                throw new InvalidArgumentException("Invalid city catalog row at index {$index}.");
            }

            $id = (int) $item['id'];
            if (isset($seenIds[$id])) {
                throw new InvalidArgumentException("Duplicate city catalog ID {$id}.");
            }
            $seenIds[$id] = true;

            $name = trim($item['name']);
            $state = trim($item['state']);
            $place = $name."\0".$state;
            if (isset($newIdsByPlace[$place])) {
                continue;
            }

            $newIdsByPlace[$place] = $id;
            $cities[] = [
                'id' => $id,
                'name' => $name,
                'state' => $state,
                'country' => 'India',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($cities, $newIdsByPlace): void {
            $oldPlaces = DB::table('cities')->get(['id', 'name', 'state', 'country'])
                ->keyBy('id');
            $references = [];
            foreach (['gyms', 'branches'] as $table) {
                $references[$table] = DB::table($table)
                    ->whereNotNull('city_id')
                    ->get(['id', 'city_id']);
            }

            // The FK's nullOnDelete action clears old references. Reattach only
            // exact India city/state matches after inserting the new catalog.
            DB::table('cities')->delete();
            foreach (array_chunk($cities, 100) as $chunk) {
                DB::table('cities')->insert($chunk);
            }

            foreach ($references as $table => $rows) {
                foreach ($rows as $row) {
                    $old = $oldPlaces->get($row->city_id);
                    if ($old === null || $old->country !== 'India') {
                        continue;
                    }

                    $newId = $newIdsByPlace[$old->name."\0".$old->state] ?? null;
                    if ($newId !== null) {
                        DB::table($table)->where('id', $row->id)->update(['city_id' => $newId]);
                    }
                }
            }
        });
    }
}
