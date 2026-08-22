@props([
    'pageTitle',
    'pageDescription' => null,
    'socialImage' => null,
])

@php
    $resolvedDescription = $pageDescription ?: 'Complete your gym membership enrollment securely.';
    $resolvedSocialImage = $socialImage ?: asset('images/public-site/brand/atlas-mark-512.png');
@endphp

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f172a">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $resolvedDescription }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $resolvedDescription }}">
    <meta property="og:image" content="{{ $resolvedSocialImage }}">
    <link rel="icon" type="image/png" href="{{ asset('images/public-site/brand/atlas-mark-64.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('tailadmin/fonts/tabler-icons.min.css') }}">
    @vite(['resources/css/public-entry.css'])
    <style>
        [hidden] { display: none !important; }
        button:disabled { cursor: not-allowed; opacity: .5; }
    </style>
</head>
<body class="min-h-screen bg-slate-100 font-[Outfit] text-slate-900 antialiased">
    <main data-enrollment-shell class="min-h-screen">{{ $slot }}</main>
</body>
</html>
