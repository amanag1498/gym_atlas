@props([
    'panelContext' => [],
    'pageTitle' => null,
    'breadcrumbs' => [],
])

<x-topbar :panel-context="$panelContext" :page-title="$pageTitle" :breadcrumbs="$breadcrumbs" />
