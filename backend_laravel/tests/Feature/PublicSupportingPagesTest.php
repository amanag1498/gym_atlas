<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSupportingPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_supporting_public_pages_load(): void
    {
        $this->get('/pricing')->assertOk();
        $this->get('/about')->assertOk();
        $this->get('/contact')->assertOk();
        $this->get('/privacy-policy')->assertOk();
        $this->get('/account-deletion')
            ->assertOk()
            ->assertSee('Delete your Atlas account');
        $this->get('/terms')->assertOk();
    }

    public function test_account_deletion_request_is_stored_and_returns_to_the_deletion_page(): void
    {
        $this->post('/contact', [
            'name' => 'Atlas Member',
            'email' => 'member@example.com',
            'inquiry_type' => 'support',
            'message' => 'Please delete my Atlas account and associated personal data.',
            'redirect_to' => route('public.account-deletion'),
        ])
            ->assertRedirect(route('public.account-deletion'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('contact_submissions', [
            'email' => 'member@example.com',
            'inquiry_type' => 'support',
            'status' => 'new',
        ]);
    }

    public function test_contact_submission_is_stored(): void
    {
        $this->post('/contact', [
            'name' => 'Public User',
            'email' => 'public@example.com',
            'phone' => '9876543210',
            'inquiry_type' => 'gym',
            'message' => 'I want to onboard my gym.',
        ])
            ->assertRedirect('/contact?inquiry_type=gym')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('contact_submissions', [
            'name' => 'Public User',
            'email' => 'public@example.com',
            'inquiry_type' => 'gym',
            'status' => 'new',
        ]);
    }

    public function test_contact_submission_validation_errors_redirect_back(): void
    {
        $this->from('/contact')
            ->post('/contact', [
                'name' => '',
                'email' => 'not-an-email',
                'inquiry_type' => 'invalid',
                'message' => '',
            ])
            ->assertRedirect('/contact')
            ->assertSessionHasErrors(['name', 'email', 'inquiry_type', 'message']);
    }
}
