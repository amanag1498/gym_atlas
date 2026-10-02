<x-admin.app-layout
    :page-title="$pageTitle ?? config('app.name')"
    :panel-context="$panelContext ?? []"
    :breadcrumbs="$breadcrumbs ?? []"
    :full-width="$panelFullWidth ?? false"
>
    @yield('content')
</x-admin.app-layout>
