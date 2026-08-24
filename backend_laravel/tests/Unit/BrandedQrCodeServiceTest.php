<?php

namespace Tests\Unit;

use App\Services\Qr\BrandedQrCodeService;
use Tests\TestCase;

class BrandedQrCodeServiceTest extends TestCase
{
    public function test_compact_code_contains_the_atlas_mark_and_valid_svg(): void
    {
        $svg = app(BrandedQrCodeService::class)->code('https://gymatlas.in/events/test-token');

        $this->assertNotFalse(simplexml_load_string($svg));
        $this->assertStringContainsString('viewBox="0 0 816 816"', $svg);
        $this->assertStringContainsString('data:image/png;base64,', $svg);
        $this->assertStringContainsString('#1e2987', $svg);
    }

    public function test_print_poster_is_branded_and_escapes_dynamic_copy(): void
    {
        $svg = app(BrandedQrCodeService::class)->poster(
            'https://gymatlas.in/events/test-token',
            'ATLAS EVENT',
            'Strength & <Mobility>',
            'Monday · 6:00 PM',
        );

        $this->assertNotFalse(simplexml_load_string($svg));
        $this->assertStringContainsString('width="1080" height="1350"', $svg);
        $this->assertStringContainsString('GymAtlas', $svg);
        $this->assertStringContainsString('Open your camera and scan to continue', $svg);
        $this->assertStringContainsString('Strength &amp; &lt;Mobility&gt;', $svg);
        $this->assertStringNotContainsString('Strength & <Mobility>', $svg);
        $this->assertStringContainsString('transform="translate(180 414) scale(0.882352941)"', $svg);
        $this->assertStringNotContainsString('data:image/svg+xml;base64,', $svg);
    }
}
