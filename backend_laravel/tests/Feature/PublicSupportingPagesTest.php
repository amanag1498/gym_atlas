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
        $this->get('/terms')->assertOk();
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
