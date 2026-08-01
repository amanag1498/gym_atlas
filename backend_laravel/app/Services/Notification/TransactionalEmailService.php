<?php

namespace App\Services\Notification;

use App\Mail\TransactionalNotificationMail;
use App\Models\Branch;
use App\Models\EmailDelivery;
use App\Models\Gym;
use App\Models\User;
use App\Services\Gym\GymSettingService;
use App\Services\Platform\PlatformSettingService;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TransactionalEmailService
{
    public function __construct(
        private readonly PlatformSettingService $platformSettingService,
        private readonly GymSettingService $gymSettingService,
    ) {}

    /** @param list<string> $lines */
    public function send(?User $user, string $subject, string $intro, array $lines = [], ?int $gymId = null, string $category = 'system', array $context = []): void
    {
        $this->sendTo($user?->email, $subject, $intro, $lines, $gymId, $category, [
            ...$context,
            'recipient_name' => $context['recipient_name'] ?? $user?->name,
        ]);
    }

    /** @param list<string> $lines */
    public function sendTo(?string $email, string $subject, string $intro, array $lines = [], ?int $gymId = null, string $category = 'system', array $context = []): void
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        if (! $this->isEnabled($gymId)) {
            $this->record($email, $subject, $category, $gymId, 'skipped');

            return;
        }

        try {
            Mail::to($email)->send(new TransactionalNotificationMail(
                $subject,
                $intro,
                $lines,
                $this->templateContext($gymId, $category, $context),
            ));
            $this->record($email, $subject, $category, $gymId, 'sent');
        } catch (\Throwable $exception) {
            report($exception);
            Log::warning('Transactional email could not be sent.', ['email' => $email, 'subject' => $subject]);
            $this->record($email, $subject, $category, $gymId, 'failed', $exception->getMessage());
        }
    }

    public function sendMailableTo(?string $email, Mailable $mailable, string $subject, ?int $gymId = null, string $category = 'system'): void
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        if (! $this->isEnabled($gymId)) {
            $this->record($email, $subject, $category, $gymId, 'skipped');

            return;
        }

        try {
            Mail::to($email)->send($mailable);
            $this->record($email, $subject, $category, $gymId, 'sent');
        } catch (\Throwable $exception) {
            report($exception);
            Log::warning('Transactional email could not be sent.', ['email' => $email, 'subject' => $subject]);
            $this->record($email, $subject, $category, $gymId, 'failed', $exception->getMessage());
        }
    }

    public function isEnabled(?int $gymId = null): bool
    {
        return $this->emailIsEnabled($gymId);
    }

    private function emailIsEnabled(?int $gymId): bool
    {
        if (! (bool) ($this->platformSettingService->all()['transactional_email_enabled'] ?? true)) {
            return false;
        }
        if (! $gymId || ! ($gym = Gym::query()->find($gymId))) {
            return true;
        }

        return (bool) ($this->gymSettingService->all($gym)['transactional_email_enabled'] ?? true);
    }

    /** @param array<string, mixed> $context */
    private function templateContext(?int $gymId, string $category, array $context): array
    {
        $gym = $gymId ? Gym::query()->find($gymId) : null;
        $branch = ! empty($context['branch_id'])
            ? Branch::query()->where('gym_id', $gymId)->find($context['branch_id'])
            : null;

        return [
            'platform_name' => config('app.name', 'Atlas'),
            'brand_name' => $gym?->name ?? config('app.name', 'Atlas'),
            'gym_name' => $gym?->name,
            'gym_logo_url' => $gym?->logo_url,
            'branch_name' => $branch?->name ?? ($context['branch_name'] ?? null),
            'category' => $category,
            ...$context,
        ];
    }

    private function record(string $email, string $subject, string $category, ?int $gymId, string $status, ?string $error = null): void
    {
        EmailDelivery::query()->create(['gym_id' => $gymId, 'recipient_email' => $email, 'category' => $category, 'subject' => $subject, 'status' => $status, 'error_message' => $error, 'sent_at' => $status === 'sent' ? now() : null]);
    }
}
