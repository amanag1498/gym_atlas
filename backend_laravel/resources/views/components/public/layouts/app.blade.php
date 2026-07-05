@include('public.layouts.app', [
    'pageTitle' => $pageTitle ?? null,
    'pageDescription' => $pageDescription ?? null,
    'slot' => $slot,
])
