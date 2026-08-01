<?php

namespace Tests\Feature;

use App\Mail\TransactionalNotificationMail;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\User;
use App\Services\Notification\TransactionalEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailTemplateBrandingFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_transactional_template_uses_gym_branch_and_recipient_context(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $member = User::factory()->create(['name' => 'Priya Member']);
        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Summit Strength Club',
            'slug' => 'summit-strength-'.str()->random(6),
            'status' => 'active',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Indiranagar Studio',
            'slug' => 'indiranagar-studio-'.str()->random(6),
            'status' => 'active',
            'is_active' => true,
        ]);

        app(TransactionalEmailService::class)->send(
            $member,
            'Payment received — '.$gym->name,
            'Your payment was recorded successfully.',
            ['Plan: Elite Annual', 'Amount: 12,000.00'],
            $gym->id,
            'payment_receipt',
            ['branch_id' => $branch->id, 'category_label' => 'Payment receipt'],
        );

        Mail::assertSent(TransactionalNotificationMail::class, function (TransactionalNotificationMail $mail) use ($branch, $gym): bool {
            $html = $mail->render();

            return $mail->context['brand_name'] === $gym->name
                && $mail->context['branch_name'] === $branch->name
                && str_contains($html, 'Priya Member')
                && str_contains($html, 'Elite Annual')
                && str_contains($html, $gym->name)
                && str_contains($html, $branch->name);
        });
    }
}
