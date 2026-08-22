<?php

namespace App\Services\WhatsApp;

use App\Models\Notification;
use App\Models\WhatsAppTemplate;
use Illuminate\Validation\ValidationException;

class WhatsAppTemplateParameterService
{
    public const ALLOWED_PLACEHOLDERS = [
        '{member_name}',
        '{notification_title}',
        '{notification_message}',
        '{gym_name}',
        '{branch_name}',
    ];

    public function validate(WhatsAppTemplate $template, array $values): array
    {
        $expected = $this->variableCount($template);
        $values = array_values(array_map(static fn (mixed $value): string => trim((string) $value), $values));

        if (count($values) !== $expected) {
            throw ValidationException::withMessages([
                'configuration.template_parameter_values' => ["This template requires exactly {$expected} variable value(s)."],
            ]);
        }
        foreach ($values as $value) {
            if ($value === '') {
                throw ValidationException::withMessages([
                    'configuration.template_parameter_values' => ['Template variable values cannot be empty.'],
                ]);
            }
            preg_match_all('/\{[^{}]+\}/', $value, $matches);
            foreach ($matches[0] ?? [] as $placeholder) {
                if (! in_array($placeholder, self::ALLOWED_PLACEHOLDERS, true)) {
                    throw ValidationException::withMessages([
                        'configuration.template_parameter_values' => ["Unsupported placeholder {$placeholder}."],
                    ]);
                }
            }
        }

        return $values;
    }

    public function components(Notification $notification, array $values): array
    {
        return $this->componentsFromReplacements($values, [
            '{member_name}' => $notification->user->name ?: 'Member',
            '{notification_title}' => $notification->title,
            '{notification_message}' => $notification->body,
            '{gym_name}' => (string) ($notification->data['gym_name'] ?? 'your gym'),
            '{branch_name}' => (string) ($notification->data['branch_name'] ?? 'your branch'),
        ]);
    }

    public function componentsFromReplacements(array $values, array $replacements): array
    {
        if ($values === []) {
            return [];
        }

        return [[
            'type' => 'body',
            'parameters' => array_map(
                static fn (string $value): array => [
                    'type' => 'text',
                    'text' => strtr($value, $replacements),
                ],
                $values,
            ),
        ]];
    }

    public function variableCount(WhatsAppTemplate $template): int
    {
        $body = collect($template->components ?? [])->first(
            fn (array $component): bool => strtoupper((string) ($component['type'] ?? '')) === 'BODY',
        );
        preg_match_all('/\{\{(\d+)\}\}/', (string) ($body['text'] ?? ''), $matches);

        return count(array_unique($matches[1] ?? []));
    }
}
