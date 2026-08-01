@props([
    'pageTitle' => null,
    'pageDescription' => null,
    'canonical' => null,
    'socialImage' => null,
    'robots' => 'index, follow',
])

@include('public.layouts.app', [
    'pageTitle' => $pageTitle,
    'pageDescription' => $pageDescription,
    'canonical' => $canonical,
    'socialImage' => $socialImage,
    'robots' => $robots,
    'head' => $head ?? null,
    'slot' => $slot,
])
