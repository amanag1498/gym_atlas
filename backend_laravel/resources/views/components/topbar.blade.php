@props([
    'panelContext' => [],
    'pageTitle' => 'Dashboard',
    'breadcrumbs' => [],
])

@include('web.partials.topbar', [
    'panelContext' => $panelContext,
    'pageTitle' => $pageTitle,
    'breadcrumbs' => $breadcrumbs,
])
