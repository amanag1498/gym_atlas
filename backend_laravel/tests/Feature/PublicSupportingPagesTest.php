<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSupportingPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_supporting_public_pages_load(): void
    {
        $this->get('/product')->assertOk()->assertSee('One connected ecosystem');
        $this->get('/member-app')->assertOk()->assertSee('Atlas Member App');
        $this->get('/trainer-app')->assertOk()->assertSee('Atlas Trainer App');
        $this->get('/gym-management')->assertOk()->assertSee('Gym management');
        $this->get('/platform-administration')
            ->assertStatus(301)
            ->assertRedirect('/product');
        $this->get('/how-it-works')->assertOk()->assertSee('How Atlas works');
        $this->get('/faq')->assertOk()->assertSee('FAQPage');
        $this->get('/pricing')->assertOk();
        $this->get('/about')->assertOk();
        $this->get('/contact')->assertOk();
        $this->get('/privacy-policy')
            ->assertOk()
            ->assertSee('Data retention schedule')
            ->assertSee('within 30 calendar days')
            ->assertSee('within 90 days')
            ->assertSee('Atlas Member deletion page')
            ->assertSee('Atlas Trainer deletion page');
        $this->get('/account-deletion')
            ->assertOk()
            ->assertSee('Delete your Atlas account')
            ->assertSee('What is deleted')
            ->assertSee('What may be retained')
            ->assertSee('within 30 calendar days');
        $this->get('/terms')->assertOk();
    }

    public function test_public_layout_uses_public_navigation_runtime_and_skip_link(): void
    {
        $this->get('/about')
            ->assertOk()
            ->assertSee('Skip to main content')
            ->assertSee('id="public-main-content"', false)
            ->assertSee(asset('js/public.js'))
            ->assertDontSee('resources/js/app.js');
    }

    public function test_public_seo_endpoints_are_available(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(route('public.product'))
            ->assertSee(route('public.gyms.index'));

        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee(route('public.sitemap'));
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
