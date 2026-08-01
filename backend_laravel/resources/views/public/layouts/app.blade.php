@props([
    'pageTitle' => null,
    'pageDescription' => null,
    'canonical' => null,
    'socialImage' => null,
    'socialImageAlt' => 'Atlas fitness platform',
    'robots' => 'index, follow',
    'schemas' => [],
])

@php
    $siteName = 'Atlas';
    $defaultDescription = 'Discover gyms, manage fitness operations, coach members and track progress with the connected Atlas fitness ecosystem.';
    $resolvedTitle = filled($pageTitle) ? trim($pageTitle).' | '.$siteName : $siteName.' | Fitness, connected';
    $resolvedDescription = filled($pageDescription) ? trim($pageDescription) : $defaultDescription;
    $resolvedCanonical = $canonical ?: request()->url();
    $resolvedSocialImage = $socialImage ?: asset('images/public-site/social/atlas-platform-social.jpg');
    $organizationSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => $siteName,
        'url' => route('public.home'),
        'logo' => asset('images/public-site/brand/atlas-mark-512.png'),
        'contactPoint' => [
            '@type' => 'ContactPoint',
            'contactType' => 'customer support',
            'url' => route('public.contact'),
        ],
    ];
    $applicationSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'SoftwareApplication',
        'name' => $siteName,
        'applicationCategory' => 'HealthApplication',
        'operatingSystem' => 'Web',
        'url' => route('public.home'),
        'description' => $defaultDescription,
    ];
@endphp

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $resolvedTitle }}</title>
    <meta name="description" content="{{ $resolvedDescription }}">
    <meta name="robots" content="{{ $robots }}">
    <link rel="canonical" href="{{ $resolvedCanonical }}">

    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $resolvedTitle }}">
    <meta property="og:description" content="{{ $resolvedDescription }}">
    <meta property="og:url" content="{{ $resolvedCanonical }}">
    <meta property="og:image" content="{{ $resolvedSocialImage }}">
    <meta property="og:image:alt" content="{{ $socialImageAlt }}">
    @unless ($socialImage)
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
    @endunless
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $resolvedTitle }}">
    <meta name="twitter:description" content="{{ $resolvedDescription }}">
    <meta name="twitter:image" content="{{ $resolvedSocialImage }}">

    <link rel="icon" type="image/png" href="{{ asset('images/public-site/brand/atlas-mark-64.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/public-site/brand/apple-touch-icon.png') }}">

    <script type="application/ld+json">{!! json_encode($organizationSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    <script type="application/ld+json">{!! json_encode($applicationSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @foreach ($schemas as $schema)
        <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endforeach

    @isset($head)
        {{ $head }}
    @endisset
    @stack('public-head')

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('tailadmin/fonts/tabler-icons.min.css') }}">

    <link rel="stylesheet" href="{{ asset('yogalax/assets/css/style.css') }}">

    @vite(['resources/css/public-entry.css'])

</head>

<body class="public-site">
    <a href="#public-main-content" class="public-skip-link">Skip to main content</a>
    <div class="public-shell">
        <x-public.navbar />

        <main id="public-main-content" class="public-main" tabindex="-1">
            {{ $slot }}
        </main>

        <x-public.footer />
    </div>

    <script src="{{ asset('js/public.js') }}" defer></script>
</body>
</html>
