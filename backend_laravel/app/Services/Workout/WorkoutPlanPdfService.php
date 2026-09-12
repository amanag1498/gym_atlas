<?php

namespace App\Services\Workout;

use App\Models\WorkoutPlan;

class WorkoutPlanPdfService
{
    /**
     * @return array{filename: string, content: string}
     */
    public function generate(WorkoutPlan $plan): array
    {
        $plan->loadMissing(['member', 'trainer', 'days.exercises.exercise']);

        $lines = [
            'Gym Atlas Workout Plan',
            '',
            'Plan: '.$plan->name,
            'Goal: '.($plan->goal ?: 'Not set'),
            'Difficulty: '.($plan->difficulty ?: 'Not set'),
            'Duration: '.$plan->duration_weeks.' week(s)',
            'Estimated Session: '.($plan->estimated_session_minutes ? $plan->estimated_session_minutes.' min' : 'Not set'),
            'Schedule: '.$this->joinList($plan->weekly_schedule ?? []),
            'Member: '.($plan->member?->name ?? 'Member'),
            'Trainer: '.($plan->trainer?->name ?? 'Self guided'),
            'Generated At: '.now()->format('d M Y, h:i A'),
            '',
        ];

        foreach ($plan->days as $day) {
            $lines[] = 'Day '.$day->day_number.': '.($day->label ?: ($day->focus ?: 'Workout'));
            if ($day->notes) {
                foreach ($this->wrapText('Notes: '.$day->notes, 82) as $line) {
                    $lines[] = $line;
                }
            }
            foreach ($day->exercises as $exercise) {
                $target = $this->exerciseTarget($exercise);
                $lines[] = '  - '.($exercise->exercise?->name ?? 'Exercise #'.$exercise->exercise_id).' | '.$target;
                if ($exercise->notes) {
                    foreach ($this->wrapText('    '.$exercise->notes, 82) as $line) {
                        $lines[] = $line;
                    }
                }
            }
            $lines[] = '';
        }

        if ($plan->notes) {
            $lines[] = 'Plan Notes';
            foreach ($this->wrapText($plan->notes, 82) as $line) {
                $lines[] = $line;
            }
        }

        $filename = 'workout-plan-'.$plan->id.'-'.str($plan->name)->slug()->limit(48, '').'.pdf';

        return ['filename' => $filename, 'content' => $this->buildPdf($lines)];
    }

    private function exerciseTarget($exercise): string
    {
        $mode = $exercise->tracking_mode ?: 'reps';
        $parts = [$exercise->sets.' set(s)'];
        if ($mode === 'reps') {
            $parts[] = 'reps '.($exercise->reps ?: '-');
            if ($exercise->target_weight !== null) {
                $parts[] = 'weight '.number_format((float) $exercise->target_weight, 2).' kg';
            }
        } elseif ($mode === 'timed') {
            $parts[] = 'duration '.$exercise->planned_duration_seconds.' sec';
        } elseif ($mode === 'distance') {
            $parts[] = 'distance '.number_format((float) $exercise->planned_distance_meters, 0).' m';
        } else {
            $parts[] = 'mode '.$mode;
        }
        if ($exercise->rest_seconds !== null) {
            $parts[] = 'rest '.$exercise->rest_seconds.' sec';
        }
        if ($exercise->group_key) {
            $parts[] = ($exercise->group_type ?: 'group').' '.$exercise->group_key;
        }

        return implode(', ', $parts);
    }

    /** @param array<int, mixed> $items */
    private function joinList(array $items): string
    {
        $items = array_values(array_filter(array_map('strval', $items)));

        return $items === [] ? 'Not fixed' : implode(', ', $items);
    }

    /** @param list<string> $lines */
    private function buildPdf(array $lines): string
    {
        $pages = array_chunk($lines, 52);
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Count '.count($pages).' /Kids ['.implode(' ', array_map(fn ($index) => (3 + ($index * 2)).' 0 R', array_keys($pages))).'] >>',
        ];
        $next = 3;
        foreach ($pages as $pageLines) {
            $contentObject = $next + 1;
            $stream = "BT\n/F1 10 Tf\n13 TL\n1 0 0 1 44 804 Tm\n".implode("\nT*\n", array_map(fn ($line) => '('.$this->escapeText($line).') Tj', $pageLines))."\nET";
            $objects[$next] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 '.(3 + (count($pages) * 2)).' 0 R >> >> /Contents '.$contentObject.' 0 R >>';
            $objects[$contentObject] = '<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream";
            $next += 2;
        }
        $objects[$next] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number." 0 obj\n".$body."\nendobj\n";
        }
        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($objects as $number => $_body) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$number])."\n";
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xrefOffset."\n%%EOF";

        return $pdf;
    }

    /** @return list<string> */
    private function wrapText(string $text, int $limit): array
    {
        $clean = preg_replace('/\s+/', ' ', trim($text)) ?: '';

        return $clean === '' ? [''] : explode("\n", wordwrap($clean, $limit, "\n", true));
    }

    private function escapeText(string $text): string
    {
        return str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\(', '\)'],
            preg_replace('/[^\x20-\x7E]/', ' ', $text) ?? $text,
        );
    }
}
