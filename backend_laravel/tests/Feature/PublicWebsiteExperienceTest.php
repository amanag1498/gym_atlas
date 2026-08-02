<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Gym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicWebsiteExperienceTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('publicPageProvider')]
    public function test_public_pages_share_the_premium_shell_and_metadata(string $routeName, string $heading): void
    {
        $response = $this->get(route($routeName));

        $response
            ->assertOk()
            ->assertSee($heading)
            ->assertSee('aria-label="Primary navigation"', false)
            ->assertSee('aria-label="Footer navigation"', false)
            ->assertSee('Skip to main content')
            ->assertSee('id="public-main-content"', false)
            ->assertSee('Fitness, connected')
            ->assertSee('<meta name="description"', false)
            ->assertSee('<meta property="og:title"', false)
            ->assertSee('<meta property="og:image"', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
            ->assertSee('<link rel="canonical" href="'.route($routeName).'">', false);

        $structuredData = $this->structuredDataFrom($response->getContent());
        $types = array_column($structuredData, '@type');

        $this->assertContains('Organization', $types, "{$routeName} is missing Organization JSON-LD.");
        $this->assertContains('SoftwareApplication', $types, "{$routeName} is missing SoftwareApplication JSON-LD.");
    }

    public static function publicPageProvider(): array
    {
        return [
            'home' => ['public.home', 'Start your fitness journey. Connect the ecosystem when you need it.'],
            'product' => ['public.product', 'From finding a gym to managing every member journey.'],
            'member app' => ['public.member-app', 'Your training and progress, with or without a gym.'],
            'trainer app' => ['public.trainer-app', 'Coach with the client context already in view.'],
            'gym management' => ['public.gym-management', 'Run the member relationship from first enquiry to renewal.'],
            'how it works' => ['public.how-it-works', 'One journey, shared by every side of the gym relationship.'],
            'find gyms' => ['public.gyms.index', 'Find the right gym, faster.'],
            'for gyms' => ['public.for-gyms', 'Run the gym from the same ecosystem that brings members in.'],
            'for trainers' => ['public.for-trainers', 'Coach with the member context already in view.'],
            'pricing' => ['public.pricing', 'Commercial terms that match the way you use Atlas.'],
            'faq' => ['public.faq', 'Understand Atlas before choosing your next step.'],
            'about' => ['public.about', 'A connected operating layer for the gym ecosystem.'],
            'contact' => ['public.contact', 'Reach the right team with the right context.'],
            'privacy' => ['public.privacy-policy', 'Privacy principles for the Atlas ecosystem.'],
            'terms' => ['public.terms', 'Use Atlas accurately, lawfully and with real intent.'],
            'account deletion' => ['public.account-deletion', 'Delete your Atlas account'],
        ];
    }

    public function test_gym_discovery_exposes_an_accessible_mobile_filter_drawer_without_changing_query_values(): void
    {
        Gym::query()->create([
            'name' => 'Atlas Strength',
            'slug' => 'atlas-strength',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
            'city' => 'Bengaluru',
            'public_listing_enabled' => true,
            'public_listing_approval_status' => 'approved',
            'trial_available' => true,
        ]);

        $response = $this->get(route('public.gyms.index', [
            'search' => 'Atlas Strength',
            'city' => 'Bengaluru',
            'distance' => 5,
            'trial_available' => 1,
        ]));

        $response
            ->assertOk()
            ->assertSee(asset('css/public-gyms.css').'?v=', false)
            ->assertSee('data-gym-filter-open', false)
            ->assertSee('aria-controls="gym-filter-panel"', false)
            ->assertSee('aria-expanded="false"', false)
            ->assertSee('id="gym-filter-panel"', false)
            ->assertSee('data-gym-filter-close', false)
            ->assertSee('data-gym-filter-backdrop', false)
            ->assertSee('4 active filters')
            ->assertSee('value="Atlas Strength"', false)
            ->assertSee('<option value="Bengaluru" selected>Bengaluru</option>', false)
            ->assertSee('name="distance" type="number" min="1" step="1" value="5"', false)
            ->assertSee('name="trial_available" value="1" checked', false)
            ->assertSee('1 gym found')
            ->assertSee(asset('images/public-site/editorial/trainer-member-coaching.webp'))
            ->assertDontSee('images.unsplash.com');

        $this->assertFileExists(public_path('css/public-gyms.css'));
        $this->assertStringContainsString(
            '.gym-discovery-v3',
            file_get_contents(public_path('css/public-gyms.css')),
        );
    }

    public function test_navigation_and_footer_expose_the_complete_public_information_architecture(): void
    {
        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee(route('public.product'))
            ->assertSee(route('public.member-app'))
            ->assertSee(route('public.trainer-app'))
            ->assertSee(route('public.gym-management'))
            ->assertSee(route('public.how-it-works'))
            ->assertSee(route('public.gyms.index'))
            ->assertSee(route('public.pricing'))
            ->assertSee(route('public.faq'))
            ->assertSee(route('public.about'))
            ->assertSee(route('public.contact'))
            ->assertSee(route('public.privacy-policy'))
            ->assertSee(route('public.terms'))
            ->assertSee(route('public.account-deletion'))
            ->assertSee(route('web.gym.login'));
    }

    public function test_individual_access_verification_and_whatsapp_are_explained_consistently(): void
    {
        $whatsappUrl = 'https://wa.me/917451008842';

        $this->get(route('public.pricing'))
            ->assertOk()
            ->assertSee('Use the Member App independently')
            ->assertSee('Coach independently after verification')
            ->assertSee('before independent coaching access is enabled to add members and manage their plans')
            ->assertSee($whatsappUrl, false)
            ->assertSee('public-cta-compact', false)
            ->assertSee('public-footer-contact-strip', false)
            ->assertDontSee('public-footer-cta', false);

        $this->get(route('public.member-app'))
            ->assertOk()
            ->assertSee('Start individually with personal workouts, diet plans and progress tracking')
            ->assertSee('Connect a gym later');

        $this->get(route('public.trainer-app'))
            ->assertOk()
            ->assertSee('Platform verification separately unlocks personal member invitations and plans')
            ->assertSee($whatsappUrl, false);

        $this->get(route('public.for-trainers'))
            ->assertOk()
            ->assertSee('Join independently or through your gym')
            ->assertSee('This does not create a public marketplace profile')
            ->assertSee($whatsappUrl, false);

        $this->get(route('public.contact'))
            ->assertOk()
            ->assertSee('WhatsApp')
            ->assertSee($whatsappUrl, false);
    }

    public function test_platform_administration_is_not_exposed_by_the_public_marketing_website(): void
    {
        $this->assertFalse(Route::has('public.platform-administration'));

        $this->get('/platform-administration')
            ->assertStatus(301)
            ->assertRedirect('/product');

        $publicRoutes = [
            'public.home',
            'public.product',
            'public.member-app',
            'public.trainer-app',
            'public.gym-management',
            'public.how-it-works',
            'public.for-gyms',
            'public.for-trainers',
            'public.pricing',
            'public.faq',
            'public.about',
            'public.contact',
            'public.privacy-policy',
            'public.terms',
            'public.account-deletion',
        ];

        foreach ($publicRoutes as $routeName) {
            $this->get(route($routeName))
                ->assertOk()
                ->assertDontSee('Platform Admin')
                ->assertDontSee('Platform Administration')
                ->assertDontSee('/platform-administration');
        }

        $this->get(route('public.sitemap'))
            ->assertOk()
            ->assertDontSee('/platform-administration');
    }

    public function test_faq_visible_content_and_schema_stay_in_sync(): void
    {
        $response = $this->get(route('public.faq'))
            ->assertOk()
            ->assertSee('data-faq-search', false)
            ->assertSee('data-faq-filter="member"', false)
            ->assertSee('data-faq-item', false)
            ->assertSee('data-faq-empty', false);
        $structuredData = collect($this->structuredDataFrom($response->getContent()));
        $faqSchema = $structuredData->firstWhere('@type', 'FAQPage');

        $this->assertIsArray($faqSchema);
        $this->assertNotEmpty($faqSchema['mainEntity'] ?? []);
        $this->assertGreaterThanOrEqual(8, count($faqSchema['mainEntity']));

        foreach ($faqSchema['mainEntity'] as $entry) {
            $this->assertSame('Question', $entry['@type'] ?? null);
            $this->assertNotEmpty($entry['name'] ?? null);
            $this->assertSame('Answer', $entry['acceptedAnswer']['@type'] ?? null);
            $this->assertNotEmpty($entry['acceptedAnswer']['text'] ?? null);
            $response->assertSeeText($entry['name']);
            $response->assertSeeText($entry['acceptedAnswer']['text']);
        }
    }

    public function test_sitemap_contains_every_indexable_static_page_and_public_gym_only(): void
    {
        $publicGym = Gym::query()->create([
            'name' => 'Sitemap Public Gym',
            'slug' => 'sitemap-public-gym',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
            'public_listing_enabled' => true,
            'public_listing_approval_status' => 'approved',
        ]);

        Gym::query()->create([
            'name' => 'Sitemap Hidden Gym',
            'slug' => 'sitemap-hidden-gym',
            'timezone' => 'Asia/Kolkata',
            'status' => 'inactive',
            'approval_status' => 'approved',
            'is_active' => false,
            'public_listing_enabled' => false,
            'public_listing_approval_status' => 'pending',
        ]);

        Gym::query()->create([
            'name' => 'Sitemap Rejected Gym',
            'slug' => 'sitemap-rejected-gym',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'approval_status' => 'rejected',
            'is_active' => true,
            'public_listing_enabled' => true,
            'public_listing_approval_status' => 'approved',
        ]);

        $response = $this->get(route('public.sitemap'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml, 'The sitemap response must be valid XML.');

        $locations = [];
        foreach ($xml->url as $entry) {
            $locations[] = (string) $entry->loc;
        }

        $expectedStaticRoutes = [
            'public.home',
            'public.product',
            'public.member-app',
            'public.trainer-app',
            'public.gym-management',
            'public.how-it-works',
            'public.gyms.index',
            'public.for-gyms',
            'public.for-trainers',
            'public.pricing',
            'public.about',
            'public.faq',
            'public.contact',
            'public.privacy-policy',
            'public.terms',
            'public.account-deletion',
        ];

        foreach ($expectedStaticRoutes as $routeName) {
            $this->assertContains(route($routeName), $locations, "The sitemap is missing {$routeName}.");
        }

        $this->assertContains(route('public.gyms.show', $publicGym->slug), $locations);
        $this->assertNotContains(route('public.gyms.show', 'sitemap-hidden-gym'), $locations);
        $this->assertNotContains(route('public.gyms.show', 'sitemap-rejected-gym'), $locations);
        $this->assertSame($locations, array_values(array_unique($locations)), 'Sitemap URLs must not be duplicated.');
    }

    public function test_robots_file_allows_public_crawling_and_points_to_the_sitemap(): void
    {
        $this->get(route('public.robots'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee("User-agent: *\nAllow: /", false)
            ->assertSee('Sitemap: '.route('public.sitemap'), false);
    }

    public function test_atlas_product_and_brand_assets_referenced_by_key_pages_exist_locally(): void
    {
        $routeNames = [
            'public.home',
            'public.product',
            'public.member-app',
            'public.trainer-app',
            'public.gym-management',
            'public.how-it-works',
            'public.gyms.index',
            'public.for-gyms',
            'public.for-trainers',
            'public.pricing',
            'public.faq',
            'public.about',
            'public.contact',
            'public.privacy-policy',
            'public.terms',
            'public.account-deletion',
        ];
        $assetPaths = [];

        foreach ($routeNames as $routeName) {
            $html = $this->get(route($routeName))->assertOk()->getContent();
            $this->assertStringNotContainsString('images.unsplash.com', $html, "{$routeName} must not depend on placeholder Unsplash imagery.");
            preg_match_all(
                '~(?:src|content)=["\'](?:https?://[^/]+)?(/images/(?:product|public-site)/[^"\']+)["\']~',
                $html,
                $matches,
            );
            $assetPaths = [...$assetPaths, ...$matches[1]];
        }

        $assetPaths = array_values(array_unique(array_map(
            static fn (string $path): string => rawurldecode(parse_url($path, PHP_URL_PATH) ?: $path),
            $assetPaths,
        )));

        $this->assertNotEmpty($assetPaths, 'Expected rendered pages to reference the local Atlas visual library.');
        $this->assertGreaterThanOrEqual(8, count($assetPaths), 'The product story should retain its visual coverage.');
        $this->assertContains('/images/public-site/brand/atlas-mark-64.png', $assetPaths);
        $this->assertContains('/images/public-site/social/atlas-platform-social.jpg', $assetPaths);
        $this->assertContains('/images/product/member/dashboard-720.webp', $assetPaths);
        $this->assertContains('/images/product/trainer/dashboard-720.webp', $assetPaths);
        $this->assertContains('/images/public-site/editorial/trainer-member-coaching.webp', $assetPaths);
        $this->assertContains('/images/public-site/editorial/gym-operations-team.webp', $assetPaths);

        foreach ($assetPaths as $assetPath) {
            $this->assertFileExists(public_path(ltrim($assetPath, '/')), "Missing rendered public asset: {$assetPath}");
        }
    }

    #[DataProvider('leadFormProvider')]
    public function test_audience_lead_forms_preserve_their_routing_contract(
        string $pageRoute,
        string $inquiryType,
        string $fragment,
    ): void {
        $response = $this->get(route($pageRoute))->assertOk();
        $redirectTo = route($pageRoute).$fragment;

        $response
            ->assertSee('method="POST" action="'.route('public.contact.store').'"', false)
            ->assertSee('name="inquiry_type" value="'.$inquiryType.'"', false)
            ->assertSee('name="redirect_to" value="'.$redirectTo.'"', false)
            ->assertSee('name="name"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="message"', false);

        $email = "{$inquiryType}.lead@example.com";

        $this->post(route('public.contact.store'), [
            'name' => ucfirst($inquiryType).' Lead',
            'email' => $email,
            'phone' => '9876543210',
            'inquiry_type' => $inquiryType,
            'message' => "Please help with my {$inquiryType} access path.",
            'redirect_to' => $redirectTo,
        ])
            ->assertRedirect($redirectTo)
            ->assertSessionHas('success');

        $this->assertDatabaseHas('contact_submissions', [
            'email' => $email,
            'inquiry_type' => $inquiryType,
            'status' => 'new',
        ]);
    }

    public static function leadFormProvider(): array
    {
        return [
            'gym onboarding' => ['public.for-gyms', 'gym', '#lead-form'],
            'trainer access' => ['public.for-trainers', 'trainer', '#trainer-access'],
            'account deletion' => ['public.account-deletion', 'support', ''],
        ];
    }

    public function test_public_gym_trial_form_and_submission_contract_remain_available(): void
    {
        $gym = Gym::query()->create([
            'name' => 'Experience Trial Gym',
            'slug' => 'experience-trial-gym',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
            'public_listing_enabled' => true,
            'public_listing_approval_status' => 'approved',
            'trial_available' => true,
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Main Branch',
            'slug' => 'main-branch',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->get(route('public.gyms.show', $gym->slug))
            ->assertOk()
            ->assertSee('action="'.route('public.gyms.trial-request', $gym->slug).'"', false)
            ->assertSee('name="request_type"', false)
            ->assertSee('name="name"', false)
            ->assertSee('name="phone"', false);

        $this->post(route('public.gyms.trial-request', $gym->slug), [
            'request_type' => 'trial',
            'name' => 'Website Trial Lead',
            'phone' => '9999999999',
            'email' => 'website.trial@example.com',
        ])
            ->assertRedirect(route('public.gyms.show', $gym->slug).'#request-trial')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('trial_requests', [
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Website Trial Lead',
            'phone' => '9999999999',
            'status' => 'pending',
        ]);
    }

    public function test_public_gym_profile_exposes_health_club_and_breadcrumb_schema(): void
    {
        $gym = Gym::query()->create([
            'name' => 'Schema Fitness Club',
            'slug' => 'schema-fitness-club',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
            'public_listing_enabled' => true,
            'public_listing_approval_status' => 'approved',
            'city' => 'Mumbai',
        ]);

        $response = $this->get(route('public.gyms.show', $gym->slug))->assertOk();
        $structuredData = collect($this->structuredDataFrom($response->getContent()));

        $this->assertSame('Schema Fitness Club', $structuredData->firstWhere('@type', 'HealthClub')['name'] ?? null);
        $this->assertCount(3, $structuredData->firstWhere('@type', 'BreadcrumbList')['itemListElement'] ?? []);
        $response
            ->assertSee('og:image:alt', false)
            ->assertSee(asset('css/public-gyms.css').'?v=', false);

        $this->assertStringContainsString(
            '.gym-profile-v3',
            file_get_contents(public_path('css/public-gyms.css')),
        );
    }

    public function test_contact_form_does_not_honor_an_external_redirect_target(): void
    {
        $this->post(route('public.contact.store'), [
            'name' => 'Safe Redirect User',
            'email' => 'safe.redirect@example.com',
            'inquiry_type' => 'user',
            'message' => 'I need help with my member account.',
            'redirect_to' => 'https://example.com/untrusted',
        ])
            ->assertRedirect(route('public.contact', ['inquiry_type' => 'user']))
            ->assertSessionHas('success');
    }

    private function structuredDataFrom(string $html): array
    {
        preg_match_all(
            '~<script\s+type=["\']application/ld\+json["\']>(.*?)</script>~s',
            $html,
            $matches,
        );

        return array_map(function (string $json): array {
            $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            $this->assertIsArray($decoded);

            return $decoded;
        }, $matches[1]);
    }
}
