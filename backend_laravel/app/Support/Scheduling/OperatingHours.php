<?php

namespace App\Support\Scheduling;

use Carbon\Carbon;

class OperatingHours
{
    /**
     * @var list<string>
     */
    public const DAYS = [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
    ];

    /**
     * @param  array<string, mixed>|null  $timings
     * @param  list<string>  $weeklyOff
     * @return array<string, list<array{open: string, close: string}>>
     */
    public static function normalize(?array $timings, array $weeklyOff = []): array
    {
        $normalized = [];
        $closedDays = collect($weeklyOff)
            ->map(fn (mixed $day): string => strtolower(trim((string) $day)))
            ->filter(fn (string $day): bool => in_array($day, self::DAYS, true))
            ->values()
            ->all();

        foreach (self::DAYS as $day) {
            if (in_array($day, $closedDays, true)) {
                $normalized[$day] = [];
                continue;
            }

            $slots = self::normalizeDaySlots($timings[$day] ?? null);
            $normalized[$day] = $slots;
        }

        return $normalized;
    }

    /**
     * @param  array<string, list<array{open: string, close: string}>>  $timings
     * @return list<string>
     */
    public static function weeklyOffFromTimings(array $timings): array
    {
        return collect(self::DAYS)
            ->filter(fn (string $day): bool => empty($timings[$day] ?? []))
            ->values()
            ->all();
    }

    /**
     * @return array{opening_time: string|null, closing_time: string|null}
     */
    public static function summarize(array $timings): array
    {
        $windows = collect($timings)
            ->flatMap(fn (mixed $daySlots) => is_array($daySlots) ? $daySlots : [])
            ->filter(fn (mixed $slot): bool => is_array($slot) && filled($slot['open'] ?? null) && filled($slot['close'] ?? null))
            ->values();

        if ($windows->isEmpty()) {
            return ['opening_time' => null, 'closing_time' => null];
        }

        return [
            'opening_time' => $windows->min('open'),
            'closing_time' => $windows->max('close'),
        ];
    }

    /**
     * @param  list<string>  $weeklyOff
     * @return array<string, list<array{open: string, close: string}>>
     */
    public static function buildFromFlat(?string $openingTime, ?string $closingTime, array $weeklyOff = []): array
    {
        if (! self::isValidTime($openingTime) || ! self::isValidTime($closingTime)) {
            return self::normalize([], $weeklyOff);
        }

        $slots = [['open' => $openingTime, 'close' => $closingTime]];
        $timings = [];

        foreach (self::DAYS as $day) {
            $timings[$day] = in_array($day, $weeklyOff, true) ? [] : $slots;
        }

        return $timings;
    }

    public static function isOpenNow(?array $timings, ?string $timezone): bool
    {
        if (! is_array($timings) || $timings === []) {
            return false;
        }

        $schedule = self::normalize($timings);
        $resolvedTimezone = $timezone ?: config('app.timezone');
        $now = Carbon::now($resolvedTimezone);
        $dayName = strtolower($now->englishDayOfWeek);

        if (self::isWithinSlots($now, $schedule[$dayName] ?? [], $resolvedTimezone)) {
            return true;
        }

        $previousDay = strtolower($now->copy()->subDay()->englishDayOfWeek);

        return self::isWithinSlots(
            $now,
            $schedule[$previousDay] ?? [],
            $resolvedTimezone,
            startsOnPreviousDay: true,
        );
    }

    /**
     * @param  list<array{open: string, close: string}>  $slots
     */
    private static function isWithinSlots(
        Carbon $now,
        array $slots,
        string $timezone,
        bool $startsOnPreviousDay = false,
    ): bool {
        foreach ($slots as $slot) {
            $open = Carbon::createFromFormat('Y-m-d H:i', sprintf(
                '%s %s',
                $startsOnPreviousDay
                    ? $now->copy()->subDay()->toDateString()
                    : $now->toDateString(),
                $slot['open'],
            ), $timezone);
            $close = Carbon::createFromFormat('Y-m-d H:i', sprintf(
                '%s %s',
                $open->toDateString(),
                $slot['close'],
            ), $timezone);
            $overnight = $close->lessThanOrEqualTo($open);

            if ($overnight) {
                $close->addDay();
            } elseif ($startsOnPreviousDay) {
                continue;
            }

            if ($now->betweenIncluded($open, $close)) {
                return true;
            }
        }

        return false;
    }

    public static function dayLabel(string $day): string
    {
        return str($day)->headline()->toString();
    }

    /**
     * @return list<string>
     */
    public static function validationErrors(mixed $timings): array
    {
        if ($timings === null || $timings === []) {
            return [];
        }

        if (! is_array($timings)) {
            return ['Schedule payload must be an array keyed by weekday.'];
        }

        $errors = [];

        foreach ($timings as $day => $slots) {
            if (! in_array((string) $day, self::DAYS, true)) {
                $errors[] = 'Unsupported day key: '.(string) $day.'.';
                continue;
            }

            if (! is_array($slots)) {
                $errors[] = self::dayLabel((string) $day).' schedule must be a list of time windows.';
                continue;
            }

            $candidateSlots = array_key_exists('open', $slots) || array_key_exists('close', $slots)
                ? [$slots]
                : $slots;

            foreach ($candidateSlots as $index => $slot) {
                if (! is_array($slot)) {
                    $errors[] = self::dayLabel((string) $day).' slot '.((int) $index + 1).' must be an object.';
                    continue;
                }

                $open = $slot['open'] ?? null;
                $close = $slot['close'] ?? null;

                if (! self::isValidTime(is_string($open) ? $open : null)) {
                    $errors[] = self::dayLabel((string) $day).' slot '.((int) $index + 1).' has an invalid opening time.';
                }

                if (! self::isValidTime(is_string($close) ? $close : null)) {
                    $errors[] = self::dayLabel((string) $day).' slot '.((int) $index + 1).' has an invalid closing time.';
                }

                if (self::isValidTime(is_string($open) ? $open : null) && self::isValidTime(is_string($close) ? $close : null) && $open === $close) {
                    $errors[] = self::dayLabel((string) $day).' slot '.((int) $index + 1).' cannot open and close at the same time.';
                }
            }
        }

        return $errors;
    }

    /**
     * @param  list<array{open: string, close: string}>  $slots
     */
    public static function formatDaySlots(array $slots): string
    {
        if ($slots === []) {
            return 'Closed';
        }

        return collect($slots)
            ->map(fn (array $slot): string => $slot['open'].' - '.$slot['close'])
            ->implode(' • ');
    }

    /**
     * @param  mixed  $slots
     * @return list<array{open: string, close: string}>
     */
    private static function normalizeDaySlots(mixed $slots): array
    {
        if (! is_array($slots) || $slots === []) {
            return [];
        }

        if (array_key_exists('open', $slots) || array_key_exists('close', $slots)) {
            $slots = [$slots];
        }

        return collect($slots)
            ->map(function (mixed $slot): ?array {
                if (! is_array($slot)) {
                    return null;
                }

                $open = self::normalizeTime($slot['open'] ?? null);
                $close = self::normalizeTime($slot['close'] ?? null);

                if (! self::isValidTime($open) || ! self::isValidTime($close) || $open === $close) {
                    return null;
                }

                return [
                    'open' => $open,
                    'close' => $close,
                ];
            })
            ->filter()
            ->sortBy('open')
            ->values()
            ->all();
    }

    private static function isValidTime(?string $value): bool
    {
        return self::normalizeTime($value) !== null;
    }

    private static function normalizeTime(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        if (preg_match('/^(?<hour>[01]\d|2[0-3]):(?<minute>[0-5]\d)(?::[0-5]\d)?$/', $value, $matches) !== 1) {
            return null;
        }

        return $matches['hour'].':'.$matches['minute'];
    }
}
