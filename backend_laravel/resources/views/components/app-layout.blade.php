@props([
    'pageTitle' => config('app.name'),
    'panelContext' => [],
    'breadcrumbs' => [],
])

<x-admin.app-layout
    :page-title="$pageTitle"
    :panel-context="$panelContext"
    :breadcrumbs="$breadcrumbs"
>
    {{ $slot }}
</x-admin.app-layout>
